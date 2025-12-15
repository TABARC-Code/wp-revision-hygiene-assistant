<?php
/**
 * Plugin Name: WP Media Hygiene Inspector
 * Plugin URI: https://github.com/TABARC-Code/wp-media-hygiene-inspector
 * Description: Audits the Media Library for broken attachments, orphaned files, oversized uploads and missing featured images. No automatic deletion. Just truth.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (c) 2025 TABARC-Code
 * Original work by TABARC-Code.
 * You may modify and redistribute this software under the terms of the
 * GNU General Public License version 3 or (at your option) any later version.
 * Keep this notice and be honest about your changes.
 *
 * Reason this exists:
 * The Media Library is where WordPress hides all the bodies.
 * Broken attachment records, files on disk with no database entry,
 * huge images straight from someone's phone, posts with no featured image.
 * None of this is fun to clean manually.
 *
 * This plugin gives me a read only audit screen with:
 * - Attachments that point at files that no longer exist.
 * - Big files that are probably bloating backups.
 * - Files on disk that do not seem to belong to any attachment.
 * - Published posts with no featured image set.
 *
 * It does not delete anything. If I break something after this, that is on me.
 *
 * TODO: add simple CSV export for each section.
 * TODO: add a filter for custom post types when checking missing featured images.
 * FIXME: scanning very large uploads trees in one request is not ideal; long term I should chunk this.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Media_Hygiene_Inspector' ) ) {

    class WP_Media_Hygiene_Inspector {

        private $screen_slug = 'wp-media-hygiene-inspector';

        // I am not going to walk millions of files in one go.
        private $max_orphan_files_list = 300;

        // Anything above this size in bytes is considered "large".
        private $large_file_threshold;

        public function __construct() {
            $this->large_file_threshold = 5 * 1024 * 1024; // 5 MB is my default "this is a bit much" line.

            add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
            add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
        }

        private function get_brand_icon_url() {
            return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
        }

        public function add_tools_page() {
            add_management_page(
                __( 'Media Hygiene Inspector', 'wp-media-hygiene-inspector' ),
                __( 'Media Hygiene', 'wp-media-hygiene-inspector' ),
                'manage_options',
                $this->screen_slug,
                array( $this, 'render_tools_page' )
            );
        }

        public function render_tools_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-media-hygiene-inspector' ) );
            }

            $upload_dir = wp_get_upload_dir();

            if ( empty( $upload_dir['basedir'] ) || ! is_dir( $upload_dir['basedir'] ) ) {
                echo '<div class="wrap">';
                echo '<h1>' . esc_html__( 'Media Hygiene Inspector', 'wp-media-hygiene-inspector' ) . '</h1>';
                echo '<p>' . esc_html__( 'Could not locate the uploads directory. Either it is misconfigured or this is a very odd setup.', 'wp-media-hygiene-inspector' ) . '</p>';
                echo '</div>';
                return;
            }

            // I pull data once and share across sections.
            $attachment_info = $this->gather_attachment_info( $upload_dir['basedir'] );
            $disk_scan       = $this->scan_uploads_directory( $upload_dir['basedir'], $attachment_info['all_paths'] );

            $broken_attachments   = $attachment_info['broken'];
            $large_attachments    = $attachment_info['large'];
            $missing_featured     = $this->find_published_posts_without_thumbnails();
            $orphaned_files       = $disk_scan['orphans'];
            $total_files_scanned  = $disk_scan['count'];
            $orphan_list_truncated = $disk_scan['truncated'];

            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Media Hygiene Inspector', 'wp-media-hygiene-inspector' ); ?></h1>
                <p>
                    This screen is my attempt to get a rough picture of how messy the Media Library has become.
                    It does not delete anything. It just points at the mess and raises an eyebrow.
                </p>

                <h2><?php esc_html_e( 'Broken attachments (database points to missing files)', 'wp-media-hygiene-inspector' ); ?></h2>
                <p>
                    These are attachment posts where WordPress thinks there is a file, but the file no longer exists
                    at the expected path under uploads. Old migrations, half finished manual cleanups and broken imports
                    are usually to blame.
                </p>
                <?php $this->render_broken_attachments_table( $broken_attachments, $upload_dir['basedir'] ); ?>

                <h2><?php esc_html_e( 'Large media files', 'wp-media-hygiene-inspector' ); ?></h2>
                <p>
                    These attachments are larger than
                    <strong><?php echo esc_html( $this->format_bytes( $this->large_file_threshold ) ); ?></strong>.
                    They might be fine. They might also be chewing through your backups and storage quota.
                </p>
                <?php $this->render_large_attachments_table( $large_attachments, $upload_dir['basedir'] ); ?>

                <h2><?php esc_html_e( 'Orphaned files on disk', 'wp-media-hygiene-inspector' ); ?></h2>
                <p>
                    These are files that live under <code><?php echo esc_html( $upload_dir['basedir'] ); ?></code>
                    but do not seem to belong to any attachment record. Old theme assets, plugin leftovers,
                    manual FTP uploads, that kind of thing.
                </p>
                <p>
                    I scanned approximately <?php echo esc_html( $total_files_scanned ); ?> files.
                    <?php
                    if ( $orphan_list_truncated ) {
                        echo esc_html__( 'The list below is truncated so this page does not melt your browser.', 'wp-media-hygiene-inspector' );
                    }
                    ?>
                </p>
                <?php $this->render_orphaned_files_table( $orphaned_files, $upload_dir['basedir'] ); ?>

                <h2><?php esc_html_e( 'Published content with no featured image', 'wp-media-hygiene-inspector' ); ?></h2>
                <p>
                    These are published posts and pages that do not have a featured image set. On some sites that is fine.
                    On others, it is a design bug waiting to be noticed.
                </p>
                <?php $this->render_missing_featured_table( $missing_featured ); ?>
            </div>
            <?php
        }

        /**
         * Gather attachment data:
         * - broken attachments
         * - large attachments
         * - set of relative paths that actually exist
         */
        private function gather_attachment_info( $uploads_basedir ) {
            global $wpdb;

            $broken = array();
            $large  = array();
            $paths  = array();

            $query_args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            );

            // Yes this might be a lot on huge sites. First version. If that hurts,
            // I can paginate in a future update.
            $attachment_ids = get_posts( $query_args );

            foreach ( $attachment_ids as $attachment_id ) {
                $file_meta = get_post_meta( $attachment_id, '_wp_attached_file', true );

                if ( empty( $file_meta ) ) {
                    // Attachment with no file stored. I am going to consider this broken as well.
                    $broken[] = array(
                        'id'          => $attachment_id,
                        'meta'        => '',
                        'exists'      => false,
                        'size'        => 0,
                        'absolute'    => '',
                    );
                    continue;
                }

                $relative_path = wp_normalize_path( $file_meta );
                $absolute_path = wp_normalize_path( trailingslashit( $uploads_basedir ) . $relative_path );

                $file_exists = is_file( $absolute_path );

                if ( $file_exists ) {
                    $paths[ $relative_path ] = true;
                    $size = filesize( $absolute_path );

                    if ( $size !== false && $size >= $this->large_file_threshold ) {
                        $large[] = array(
                            'id'          => $attachment_id,
                            'meta'        => $relative_path,
                            'exists'      => true,
                            'size'        => $size,
                            'absolute'    => $absolute_path,
                        );
                    }
                } else {
                    $broken[] = array(
                        'id'          => $attachment_id,
                        'meta'        => $relative_path,
                        'exists'      => false,
                        'size'        => 0,
                        'absolute'    => $absolute_path,
                    );
                }
            }

            return array(
                'broken'    => $broken,
                'large'     => $large,
                'all_paths' => array_keys( $paths ),
            );
        }

        /**
         * Walk the uploads directory and find files that are not in the known attachment paths list.
         *
         * I try hard not to get clever here. No deletion. Just "this file does not match any attachment meta".
         */
        private function scan_uploads_directory( $basedir, $known_relative_paths ) {
            $known_map = array();
            foreach ( $known_relative_paths as $rel ) {
                $known_map[ wp_normalize_path( $rel ) ] = true;
            }

            $orphans   = array();
            $count     = 0;
            $truncated = false;

            $root = wp_normalize_path( $basedir );

            try {
                $dir_iterator = new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS );
                $iterator     = new RecursiveIteratorIterator( $dir_iterator );

                foreach ( $iterator as $file_info ) {
                    if ( ! $file_info->isFile() ) {
                        continue;
                    }

                    $count++;

                    $absolute = wp_normalize_path( $file_info->getPathname() );
                    $relative = ltrim( str_replace( $root, '', $absolute ), '/\\' );
                    $relative = wp_normalize_path( $relative );

                    // If this relative path is in the known set, skip.
                    if ( isset( $known_map[ $relative ] ) ) {
                        continue;
                    }

                    // I am not going to list every orphan on planet earth in one request.
                    if ( count( $orphans ) < $this->max_orphan_files_list ) {
                        $orphans[] = array(
                            'relative' => $relative,
                            'absolute' => $absolute,
                            'size'     => $file_info->getSize(),
                        );
                    } else {
                        $truncated = true;
                    }
                }
            } catch ( Exception $e ) {
                // If something goes wrong, I will just return what I have.
            }

            return array(
                'orphans'   => $orphans,
                'count'     => $count,
                'truncated' => $truncated,
            );
        }

        /**
         * Find published posts and pages (and other public post types) with no featured image.
         */
        private function find_published_posts_without_thumbnails() {
            $public_types = get_post_types(
                array(
                    'public' => true,
                ),
                'names'
            );

            // Attachments are not content needing thumbnails.
            unset( $public_types['attachment'] );

            if ( empty( $public_types ) ) {
                return array();
            }

            $args = array(
                'post_type'      => $public_types,
                'post_status'    => 'publish',
                'posts_per_page' => 100,
                'meta_query'     => array(
                    array(
                        'key'     => '_thumbnail_id',
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            );

            // I cap at 100. This is meant as a sample and a hint, not an exhaustive export.
            $posts = get_posts( $args );

            return $posts;
        }

        private function render_broken_attachments_table( $broken_attachments, $basedir ) {
            if ( empty( $broken_attachments ) ) {
                echo '<p>' . esc_html__( 'No broken attachments detected based on current metadata. Either things are tidy or the real problems are elsewhere.', 'wp-media-hygiene-inspector' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Attachment', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'File meta', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Expected path', 'wp-media-hygiene-inspector' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $broken_attachments as $row ) : ?>
                    <tr>
                        <td>
                            <?php
                            $edit_link = get_edit_post_link( $row['id'] );
                            $title     = get_the_title( $row['id'] );

                            if ( $edit_link ) {
                                echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
                            } else {
                                echo esc_html( $title );
                            }
                            ?>
                            <br>
                            <span style="font-size:12px;opacity:0.7;">
                                <?php echo esc_html__( 'ID:', 'wp-media-hygiene-inspector' ) . ' ' . (int) $row['id']; ?>
                            </span>
                        </td>
                        <td>
                            <code><?php echo esc_html( $row['meta'] ); ?></code>
                        </td>
                        <td>
                            <code><?php echo esc_html( $row['absolute'] ); ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }

        private function render_large_attachments_table( $large_attachments, $basedir ) {
            if ( empty( $large_attachments ) ) {
                echo '<p>' . esc_html__( 'No attachments exceeded the large file threshold. At least storage is not the immediate problem.', 'wp-media-hygiene-inspector' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Attachment', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Relative path', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'wp-media-hygiene-inspector' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $large_attachments as $row ) : ?>
                    <tr>
                        <td>
                            <?php
                            $edit_link = get_edit_post_link( $row['id'] );
                            $title     = get_the_title( $row['id'] );

                            if ( $edit_link ) {
                                echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
                            } else {
                                echo esc_html( $title );
                            }
                            ?>
                            <br>
                            <span style="font-size:12px;opacity:0.7;">
                                <?php echo esc_html__( 'ID:', 'wp-media-hygiene-inspector' ) . ' ' . (int) $row['id']; ?>
                            </span>
                        </td>
                        <td><code><?php echo esc_html( $row['meta'] ); ?></code></td>
                        <td><?php echo esc_html( $this->format_bytes( $row['size'] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }

        private function render_orphaned_files_table( $orphans, $basedir ) {
            if ( empty( $orphans ) ) {
                echo '<p>' . esc_html__( 'No obvious orphaned files detected under uploads. Either someone has been tidy, or the real junk lives elsewhere.', 'wp-media-hygiene-inspector' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Relative path', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Size', 'wp-media-hygiene-inspector' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $orphans as $row ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $row['relative'] ); ?></code></td>
                        <td><?php echo esc_html( $this->format_bytes( $row['size'] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:12px;opacity:0.8;">
                <?php esc_html_e( 'Treat these as hints. Some plugins and themes legitimately store their own files under uploads.', 'wp-media-hygiene-inspector' ); ?>
            </p>
            <?php
        }

        private function render_missing_featured_table( $posts ) {
            if ( empty( $posts ) ) {
                echo '<p>' . esc_html__( 'No published posts or pages without featured images were found, based on the current sample. Congratulations or coincidence.', 'wp-media-hygiene-inspector' ) . '</p>';
                return;
            }

            ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Author', 'wp-media-hygiene-inspector' ); ?></th>
                        <th><?php esc_html_e( 'Published on', 'wp-media-hygiene-inspector' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $posts as $post ) : ?>
                    <tr>
                        <td>
                            <?php
                            $edit_link = get_edit_post_link( $post->ID );
                            $title     = get_the_title( $post->ID );

                            if ( $edit_link ) {
                                echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>';
                            } else {
                                echo esc_html( $title );
                            }
                            ?>
                            <br>
                            <span style="font-size:12px;opacity:0.7;"><?php echo esc_html__( 'ID:', 'wp-media-hygiene-inspector' ) . ' ' . (int) $post->ID; ?></span>
                        </td>
                        <td><code><?php echo esc_html( get_post_type( $post ) ); ?></code></td>
                        <td>
                            <?php
                            $author = get_userdata( $post->post_author );
                            echo $author ? esc_html( $author->display_name ) : esc_html__( 'Unknown', 'wp-media-hygiene-inspector' );
                            ?>
                        </td>
                        <td><?php echo esc_html( get_the_date( '', $post->ID ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:12px;opacity:0.8;">
                <?php esc_html_e( 'This list is capped to a sample for safety. If you see patterns you do not like, you will want to dig deeper with a custom query.', 'wp-media-hygiene-inspector' ); ?>
            </p>
            <?php
        }

        private function format_bytes( $bytes ) {
            $bytes = (int) $bytes;

            if ( $bytes < 1024 ) {
                return $bytes . ' B';
            }

            $units = array( 'KB', 'MB', 'GB', 'TB' );
            $value = $bytes / 1024;
            $i     = 0;

            while ( $value >= 1024 && $i < count( $units ) - 1 ) {
                $value /= 1024;
                $i++;
            }

            return number_format_i18n( $value, 2 ) . ' ' . $units[ $i ];
        }

        public function inject_plugin_list_icon_css() {
            $icon_url = esc_url( $this->get_brand_icon_url() );
            ?>
            <style>
                .wp-list-table.plugins tr[data-slug="wp-media-hygiene-inspector"] .plugin-title strong::before {
                    content: '';
                    display: inline-block;
                    vertical-align: middle;
                    width: 18px;
                    height: 18px;
                    margin-right: 6px;
                    background-image: url('<?php echo $icon_url; ?>');
                    background-repeat: no-repeat;
                    background-size: contain;
                }
            </style>
            <?php
        }
    }

    new WP_Media_Hygiene_Inspector();
}
