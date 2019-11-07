# Restricted Media (POC)

This is a proof of concept plugin to demonstrate storing designated assets in S3 and restricting access to them.

## Setup

* Run `composer install`
* Define the following constants in `wp-config.php`: `S3_UPLOADS_KEY`, `S3_UPLOADS_SECRET`, `S3_UPLOADS_REGION`, `S3_UPLOADS_BUCKET`
* Add a filter to `blackstone_store_private`. Return `true` to store the asset privately in S3. The filter is passed a file array with the name of the file.


## How It Works
Designated files are stored in S3. Those files are not accessible via the web browser. To view a private file, you would navigate to `http://url.com/private/[file_id]`. That URL validates the user before displaying the file. The POC plugin will only show private files to logged in administrators.