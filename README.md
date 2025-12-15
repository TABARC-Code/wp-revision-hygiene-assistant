<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Media Hygiene Inspector

The Media Library is where good intentions go to die.

Broken attachment records, files left behind by long dead plugins, 12 megabyte JPEGs straight from a phone camera, and posts that should have a featured image but somehow never did.

This plugin does not delete anything. That would be optimistic.

It audits. Calmly. Bluntly. Then leaves the actual destruction in your hands.

## What it does

On one screen under Tools you get four views of the mess.

### 1. Broken attachments

Attachment posts where:

- WordPress has a `_wp_attached_file` meta value  
- The file at that path does not exist in `wp-content/uploads`  

Classic symptoms of:

- Manual file deletions  
- Migrations that forgot to bring everything  
- Old backup restores  

You get:

- Attachment title and ID  
- The stored file meta  
- The expected absolute path  

What you do with that is your call.

### 2. Large media files

Attachments where:

- The file exists  
- The size is above a threshold (default 5 MB)  

These are the usual suspects when backups are huge and the site feels bloated.

You get:

- Attachment title and ID  
- Relative path in uploads  
- File size in human readable form  

Some of them are justified. Some are just laziness.

### 3. Orphaned files on disk

Files that:

- Live under the uploads directory  
- Are not referenced by any attachment `_wp_attached_file` meta  

These are:

- Old theme and plugin assets  
- Manual FTP dumps  
- Junk from experiments you forgot about  .

You see:

- Relative path under uploads  
- File size  

There is a hard cap on how many I list per scan so your browser does not weep. This is an audit, not a full forensic export.

### 4. Published content with no featured image

Published posts and pages and other public types that:

- Are status `publish`.  
- Have no `_thumbnail_id` set  

On some sites this is fine. On others it is a broken design slot.

You see:

- Title and ID  
- Post type  
- Author  
- Published date  

Enough to decide if you care.

## What it does not do

Important, so you do not assume magic.

It does not:

- Delete files  
- Delete attachments  
- Regenerate thumbnails  
- Optimise images  
- Resize anything  
- Fix migrations  
- Repair metadata  

This is an inspector, not a cleaner. You still grab the mop.

## Requirements

- WordPress 6.0 or newer  
- PHP 7.4 or newer, PHP 8 recommended  
- Ability to access Tools and manage options  

On very large sites the scans can be slow. This is version one. It is more honest than optimised.

## Installation

Clone or download the repository.

```bash
git clone https://github.com/TABARC-Code/wp-media-hygiene-inspector.git
Drop it into:

text
Copy code
wp-content/plugins/wp-media-hygiene-inspector
Activate the plugin in the admin.

Then go to:

Tools
Media Hygiene

If that menu item is missing, check that you are logged in as an administrator or someone with manage options capability.

How to use it without hurting yourself
Broken attachments
Use this when:

Images show as broken on the front end

You see attachments in the library that do nothing

Workflow:

Open the Broken attachments table.

For each entry, click through to the attachment editor.

Decide if you should:

Relink the attachment to a real file

Delete the attachment record

Ignore it if it is ancient and harmless

If there are many, do this on staging first and maybe script the deletion.

Large media files
Use this when:

Backups are huge

Hosting storage is expensive

Pages are slow because of unoptimised images

Workflow:

Scan the Large media files section.

Look at the worst offenders first.

For each big file, decide if:

It should be replaced with an optimised version

It should be archived somewhere else

It is fine as is

The plugin will not resize or recompress anything. You still need an image optimisation workflow.

Orphaned files
Use this when:

You want to know what is sitting in uploads that the database has forgotten about

Be careful. Some plugins and themes store assets under uploads on purpose.

Workflow:

Read the Orphaned files table as a hint list.

For each suspiciouss file:

Check if it is actually referenced anywhere in code or templates

Check file type and location

Decide whether to back it up and then delete it manually

If in doubt, archive rather than delete. Once gone, it is gone.

Missing featured images
Use this when:

The design expects a featured image for everything

Grid views look broken, Social sharing looks sad

Workflow:

Scan the Published content with no feaatured image table.

For each post:

Click through.

Set a featured image if appropriate

If the list is long, this is your sign that your publishing process needs clearer rules.

Performance notes
Attachments are loaded via get_posts with fields set to IDs only.

Disk scan walks the uploads directory with PHP iterators.

Orphan list is capped to a reasonable number.

On small to medium sites thsis is fine. On huge sites:,

Run this in off peak times

Expect the first scan to be slower.

Consider trimming uploads with other tools first

If it really hurts, you can fork this and add pagination, or wait for a future version where I might do that myself.

Roadmap
Things that might happen if I stay irritated enough:

CSV export per section

Controls for the large file threshold from the UI

Per post type controls for the missing featured image audit

Detection of unused image sizes for old thumbnail dimensions, A dry run checklist for scripted cleanups

Things that probably will not happen: Automatic deletion of orphans

Background cleanups that quietly remove things

Full media optimiser features that reinvent existing plugins

I want this to stay an auditor. Not an all in one media monster.
