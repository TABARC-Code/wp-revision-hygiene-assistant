# IdiotsGuide  
WP Media Hygiene Inspector

This is for the version of me who knows the Media Library is a swamp but really does not want to think about it.

No shade. Just honesty.

## What this plugin is

A flashlight.

It walks through `wp-content/uploads` and the Media Library and says:

- These records are broken.  
- These files are huge.  
- These files do not seem to belong to anything.  
- These posts have no featured image.  ,

It does not delete. It does not optimise. It does not fix.  
It just tells the truth you have been avoiding.

## Where to find it

After activation:,

1. Log into WordPress admin.  
2. Go to Tools.  
3. Click Media Hygiene.  /

If you cannot see it, you probably do not have admin level access.

## The four sections, in plain language

### 1. Broken attachments

These are the ghosts.

- WordPress has an attachment record.  
- The attachment record says the file should be at `uploads/some/path/file.jpg`.  
- That file is not there.  

Usually caused by:

- Manual file deletions from the server.  
- Botched migrations.  
- Old staging copies overwriting production.  

What you see:

- The name of the attachment.  
- Its ID.  
- The relatve file path WordPress believes in.  
- The absolute path where the file should be.

What you can do about it:

- Delete the attachment if it is clearly junk.  
- Reattach a proper file.  
- Ignore it for now and regret that later.

### 2. Large media files

These are the heavyweights.

- Files that exist.  
- Files that are above the size threhold, by default 5 MB.  

Translations:

- These are the ones bloating your backups and storage.  
- These are also the ones slowing down page loads when someone forgot to compress images.

What you see:

- Title  
- Attachment ID  
- Relative path  
- File size  

What you do:

- Decide which ones to optimise.  
- Decide which ones to archive somewhere else.  
- Decide if you want to raise or lower that 5 MB line in a future version.

### 3. Orphaned files on disk

These are the squatters.

Files that:

- Exist under `wp-content/uploads`.  
- Are not refeenced by any attachment `_wp_attached_file` meta.  

They might be:

- Old theme assets.  
- Leftover plugin uploads.  
- Manual dumps from an FTP session you pretend never happened.  

What you see:

- Relative path  
- Size  

There is a cap on how many appear per scan so the page does not explode.

What to do:

- Treat this as a list of suspects, not a list of safe deetions.  
- For each suspicious file:
  - Check if it is referenced in templates or code.  
  - If not, consider backing it up and removing it manually.  

If you start deleting blindly, that is on you.

### 4. Published content with no featured image

These are the naked posts.

- Public, published content.  
- No featured image set.  

On some sites, nobody cares.  
On others, this is why the grid looks broken and social cards look sad.

What you see:

- Title  
- ID  
- Post type  
- Author  
- Published date  

What you can do:

- Click through and set featured images where it matters.  

If this list is long, your publishing workflow needs stronger rules.

## How to use this without spiralling

Here is the safe loop.

1. Run the scan on a staging copy first.  
2. Look at Broken attachments.  
   - Clean a handful.  
3. Look at Large media.  
   - Fix the worst offenders.  
4. Look at Orphaned files.  
   - Take notes. Delete later after backups.  
5. Look at Missing featured images.  
   - Fix the obvious important ones.  

Then stop. This is grunt work. Do not try to clean five years of chaos in one sitting.

## Things to be careful about

Some warnings, from someone who has broken things this way before.

### Orphans are not always junk

Some plugins store their own data under uploads:

- Custom caches.  
- Form uploads.  
- Temporary working files that never got cleaned.  

Just because a file is not in the attachment table does not mean it is unused. Check context.

### Big files might be critical

Yes, that 12 MB hero image is ridiculous.  
It also might be the centrepiece of a home page.

Optimise. Replace. Do not just delete and hope no one notices.

### Broken attachments can be old content

Sometimes the broken entry was used in a very old page that nobody remembered.

Removing the attachment record might not break the current site. It might still break history.,

Archive before deletion. Especially on client sites.

### This does not fix storage,

This is an audit. If your storage is full, this tells you why. It does not free anything by itself.

Use this as your checklist before you start an actual cleanup strategy.

## When not to use this

You probably should not be doing media hygiene:

- In the middle of a critical launch.  
- During peak traffic.  
- When you are already debugging something else.  
- Without working backups.  

This is maintenance work. Quiet time, cup of tea, slightly annoyed mood. Not crisis mode.

## Simple mental model

If you need a one line summary of each section:

- Broken attachments = database lies.  
- Large media files = backups cry.  
- Orphaned files = uploads directory hoarding.  
- Missing featured images = front end looks half finished.  

Use that, decide what hurts most, start there.

## Final thought

If the Media Library were treated like a real asset repository instead of a dumping ground, this plugin would not be necessary.

It is not. So it is..

Run it once in a while. Learn what kind of mess you are sitting on. Then decide how brave you feel today.
