# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/), and will adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.5] - 2021-03-05
- Initial public release 🎉.

### Added
- Plugin banner and icon assets (props [@McCallumDillon](https://github.com/McCallumDillon), [@cgoozen](https://profiles.wordpress.org/cgoozen/) via [#8](https://github.com/10up/secure-media/pull/8), [#14](https://github.com/10up/secure-media/pull/14)).
- Documentation and GitHub Action updates (props [@jeffpaul](https://github.com/jeffpaul), [@dinhtungdu](https://github.com/dinhtungdu) via [#16](https://github.com/10up/secure-media/pull/16), [#17](https://github.com/10up/secure-media/pull/17)).

### Changed
- Code spacing, documentation, translated strings, formatting, and other code cleanup tasks (props [@dkotter](https://github.com/dkotter) via [#15](https://github.com/10up/secure-media/pull/15)).

### Security
- Bump `ini` from 1.3.5 to 1.3.8 (props [@dependabot](https://github.com/apps/dependabot) via [#10](https://github.com/10up/secure-media/pull/10), [#13](https://github.com/10up/secure-media/pull/13)).
- Update NPM packages for `axios` and `socket.io` to fix vulnerabilities (props [@joshuaabenazer](https://github.com/joshuaabenazer) via [#18](https://github.com/10up/secure-media/pull/18)).

## [1.0.4] - 2021-01-04
### Fixed
- Better S3 error logging (props [@tlovett1](https://github.com/tlovett1)).

## [1.0.3] - 2021-01-04
### Fixed
- Don't break old media and ensure new media has the correct visibility (props [@tlovett1](https://github.com/tlovett1)).
- Create upload sub dir if it doesn't exist (props [@tlovett1](https://github.com/tlovett1)).
- Fix public srcset urls (props [@tlovett1](https://github.com/tlovett1)).
- Fix missing setting; only delete file if it exists (props [@tlovett1](https://github.com/tlovett1)).
- Check if file exists before doing mkdir (props [@tlovett1](https://github.com/tlovett1)).

## [1.0.2] - 2020-12-22
### Fixed
- Set default bucket and make sure there's always an S3 bucket (props [@tlovett1](https://github.com/tlovett1)).
- Assorted bugs (props [@tlovett1](https://github.com/tlovett1)).

## [1.0.1] - 2020-12-10
### Fixed
- Redirect single attachment page for private media if not authorized (props [@tlovett1](https://github.com/tlovett1)).
- Assorted errors (props [@tlovett1](https://github.com/tlovett1)).

## [1.0.0] - 2020-10-26
- Initial private release of Secure Media plugin.

[Unreleased]: https://github.com/10up/secure-media/compare/trunk...develop
[1.0.5]: https://github.com/10up/secure-media/compare/1.0.4...1.0.5
[1.0.4]: https://github.com/10up/secure-media/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/10up/secure-media/compare/20f33fd...1.0.3
[1.0.2]: https://github.com/10up/secure-media/compare/9336b98...20f33fd
[1.0.1]: https://github.com/10up/secure-media/compare/99d7aae...9336b98
[1.0.0]: https://github.com/10up/secure-media/tree/99d7aaeb7deb27a78874837474986ed011f49ab1
