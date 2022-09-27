# Changelog

All notable changes to Templado are documented in this file using the [Keep a CHANGELOG](http://keepachangelog.com/) principles.


## [1.1.0] - 2022-09-27

### Added

* `JournalEntry::fromMessage()` now has an optional $traceOffset parameter to allow for better integration in 3rd party logger infrastructures 

### Fixed
* `JournalEntry`'s CODE_LINE and CODE_FILE references point to wrong location when created from message

## [1.0.0] - 2022-06-06

* Initial Release

[1.0.0]: https://github.com/theseer/journald/releases/tag/1.0.0
