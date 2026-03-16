# Speaker Portal Reconstruction

This is an AI reconstruction :/ of a WordPress plugin I wrote based on the portal requirements available in chat history, not a byte-for-byte recovery of your original plugin files.

## What it includes

- Main plugin bootstrap
- Admin settings page
- Speaker portal meta box fields
- Completion/progress calculation
- Google Sheets sync on save
- Manual sync button from the post editor

## Reconstructed assumptions

The plugin currently assumes these fields exist as post meta:

- first_name
- last_name
- email
- phone
- company
- job_title
- session_title
- session_summary
- speaker_bio
- co_presenters
- website
- linkedin
- headshot_id
- slides_id
- agreement
- internal_notes

## Google Sheets behavior

The sync writes one row per post and uses `page_id` as the unique identifier.
If a matching `page_id` exists, the row is updated.
If not, a new row is appended.

Media fields are flattened to three columns:

- `headshot_id` / `slides_id`
- `headshot_id_url` / `slides_id_url`
- `headshot_id_filename` / `slides_id_filename`

## Before production use

1. Review the field schema in `includes/class-spr-utils.php`
2. Confirm the target post types in settings
3. Paste in valid Google service account JSON
4. Share the spreadsheet with that service account email
5. Test on staging first

## Likely next edits

- Replace numeric attachment IDs with a real media picker UI
- Match your exact original field keys
- Add Dropbox / Google Drive / Asana hooks if those were in the original plugin set
- Split this into separate plugins if your old project used multiple plugin packages
