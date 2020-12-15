# Secure Media

> Store private media securely in WordPress.

This plugins stores media associated with non-public posts in S3 with private visibility. Image requests for private media are proxied through WordPress to ensure proper user capabilities. Once a post is published, all it's media is made public and transfered to the WordPress public uploads folder. Media uploaded outside of posts e.g. the media library are made private as well.

## Setup

* Install plugin.
* Configure in `Settings > Media`