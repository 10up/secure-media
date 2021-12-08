# Secure Media

> Store private media securely in WordPress.

[![Support Level](https://img.shields.io/badge/support-beta-blueviolet.svg)](#support-level) [![Release Version](https://img.shields.io/github/release/10up/secure-media.svg)](https://github.com/10up/secure-media/releases/latest) ![WordPress tested up to version](https://img.shields.io/badge/WordPress-v5.8%20tested-success.svg) [![GPLv2 License](https://img.shields.io/github/license/10up/secure-media.svg)](https://github.com/10up/secure-media/blob/develop/LICENSE.md)

This plugins stores media associated with non-public posts in S3 with private visibility. Image requests for private media are proxied through WordPress to ensure proper user capabilities. Once a post a published, all it's media is made public and transfered to the WordPress public uploads folder. Media uploaded outside of posts e.g. the media library are made private as well.

## Setup

* Install plugin.
* Configure in `Settings > Media`

## Support Level

**Beta:** This project is quite new and we're not sure what our ongoing support level for this will be. Bug reports, feature requests, questions, and pull requests are welcome. If you like this project please let us know, but be cautious using this in a Production environment!

## Like what you see?

<a href="http://10up.com/contact/"><img src="https://10up.com/uploads/2016/10/10up-Github-Banner.png" width="850" alt="Work with us at 10up"></a>
