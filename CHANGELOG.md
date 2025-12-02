# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v1.5.1] - 2025-12-02
### :bug: Bug Fixes
- [`0bfbd4c`](https://github.com/edgardmessias/glpi-singlesignon/commit/0bfbd4c353f5871c9d92bc5e83d1307be1e36763) - iframe sso connect *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`f0e6271`](https://github.com/edgardmessias/glpi-singlesignon/commit/f0e6271a8b64968a02bcfc161e0e82afc3db22cb) - CSRF *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`c5b1456`](https://github.com/edgardmessias/glpi-singlesignon/commit/c5b14569680ce12bd451cc62cc940cad74a643cb) - redirect provider *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`bdba107`](https://github.com/edgardmessias/glpi-singlesignon/commit/bdba107ee5ea5c7e4224cd0c45def0dbbfc76d41) - delete cookie *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`66773cd`](https://github.com/edgardmessias/glpi-singlesignon/commit/66773cde50b4e6ca835a5bb3ddbcfe679dcf4eb9) - fix frontend component *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`3ad880d`](https://github.com/edgardmessias/glpi-singlesignon/commit/3ad880df89129705dba84f7c1e65dcd5d0df02e8) - restore PATH_INFO callback URL for providers rejecting query strings *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`35a39b5`](https://github.com/edgardmessias/glpi-singlesignon/commit/35a39b5e6f0998bac8c737e1111aae9860fb1d55) - rebuild callback PATH_INFO when server strips it *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`d02343b`](https://github.com/edgardmessias/glpi-singlesignon/commit/d02343bf5c6f50323f80a9a02613f1a15464a3ed) - use css directory for logout-redirect.js file *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`96a2691`](https://github.com/edgardmessias/glpi-singlesignon/commit/96a269105bc50343c7aa9486b3e793aaf176680e) - add index.php security file to js directory *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`8a42472`](https://github.com/edgardmessias/glpi-singlesignon/commit/8a42472df73ee7d20329587d37aba6a57fde0fb5) - delete old markdown *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`9cab718`](https://github.com/edgardmessias/glpi-singlesignon/commit/9cab718a9e14d8096188288363d3e1826442ef51) - resolve php-cs-fixer linting issues *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*

### :recycle: Refactors
- [`1a7b34c`](https://github.com/edgardmessias/glpi-singlesignon/commit/1a7b34cc049cf945af5825788cdc2dba2ad3eeb5) - use static JS file for logout redirect instead of inline script injection *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*
- [`3a7695d`](https://github.com/edgardmessias/glpi-singlesignon/commit/3a7695dea53b024270ab8afa732954b057cf1608) - use inline script instead of external JS file *(commit by [@Killian-Aidalinfo](https://github.com/Killian-Aidalinfo))*


## [v1.4.0] - 2025-02-04
### :sparkles: New Features
- [`4901caf`](https://github.com/edgardmessias/glpi-singlesignon/commit/4901cafe3c5d3eb0f7f41e93bff4a50912c166ae) - Added es_ES translation *(commit by [@edgardmessias](https://github.com/edgardmessias))*
- [`7552525`](https://github.com/edgardmessias/glpi-singlesignon/commit/755252547056d650a3e5a4c394c2b6af865e4ede) - Added lint to provider page from plugin page *(commit by [@edgardmessias](https://github.com/edgardmessias))*
- [`135975f`](https://github.com/edgardmessias/glpi-singlesignon/commit/135975fa192032871d5bbb10e13d300a05884506) - Added license (close [#36](https://github.com/edgardmessias/glpi-singlesignon/pull/36)) *(commit by [@edgardmessias](https://github.com/edgardmessias))*
- [`51999b4`](https://github.com/edgardmessias/glpi-singlesignon/commit/51999b40dfe7fafa9473755820acdd60cdc808fe) - **locales**: french translation *(PR [#65](https://github.com/edgardmessias/glpi-singlesignon/pull/65) by [@ternium1](https://github.com/ternium1))*
- [`cae95d8`](https://github.com/edgardmessias/glpi-singlesignon/commit/cae95d8a43c9c375368c10b46c640926b690e5df) - Added create new user for Google *(PR [#70](https://github.com/edgardmessias/glpi-singlesignon/pull/70) by [@ch-tm](https://github.com/ch-tm))*
- [`144c3cd`](https://github.com/edgardmessias/glpi-singlesignon/commit/144c3cdc9c4d7915c64b21e8b64f56c522b40d90) - Improve Reverse Proxy and Plugin folder support *(PR [#103](https://github.com/edgardmessias/glpi-singlesignon/pull/103) by [@eduardomozart](https://github.com/eduardomozart))*
- [`ebff864`](https://github.com/edgardmessias/glpi-singlesignon/commit/ebff8646f933038ad94969cc1a424135381f9f9f) - Sync GLPI photo with Azure AD *(PR [#101](https://github.com/edgardmessias/glpi-singlesignon/pull/101) by [@eduardomozart](https://github.com/eduardomozart))*
- [`d45b759`](https://github.com/edgardmessias/glpi-singlesignon/commit/d45b759bc760ca1314f070796dd8cf1d51fc1368) - Automatically close stale issues *(commit by [@eduardomozart](https://github.com/eduardomozart))*
- [`cc09ff3`](https://github.com/edgardmessias/glpi-singlesignon/commit/cc09ff3e9be37856e11e1f1a5c58a2a673ef0aa4) - Automatically close stale issues *(commit by [@eduardomozart](https://github.com/eduardomozart))*
- [`fb7eae6`](https://github.com/edgardmessias/glpi-singlesignon/commit/fb7eae6ebeb95de90613ed7167995045b6f9c6dd) - Add 'debug' tab to copy SSO provider info *(commit by [@eduardomozart](https://github.com/eduardomozart))*

### :bug: Bug Fixes
- [`c7b1be1`](https://github.com/edgardmessias/glpi-singlesignon/commit/c7b1be17b3c2d80c6df31d334d3c7d3de1214a74) - Fixed redirect for default provider *(commit by [@edgardmessias](https://github.com/edgardmessias))*
- [`be22981`](https://github.com/edgardmessias/glpi-singlesignon/commit/be229815a7f026e40903c588dc1c1eb3d7fef972) - Fixed show buttons for GLPI >= 10.0 *(commit by [@edgardmessias](https://github.com/edgardmessias))*
- [`2c83a54`](https://github.com/edgardmessias/glpi-singlesignon/commit/2c83a54f013e596d28b7d52beb56145d2555f4b0) - Could not add picture see [#88](https://github.com/edgardmessias/glpi-singlesignon/pull/88) *(PR [#89](https://github.com/edgardmessias/glpi-singlesignon/pull/89) by [@invisiblemarcel](https://github.com/invisiblemarcel))*
- [`ddc9c2d`](https://github.com/edgardmessias/glpi-singlesignon/commit/ddc9c2ddc1a3dd7fe1f76c44c844b1d3e690c9d7) - Add missing string to translation *(PR [#93](https://github.com/edgardmessias/glpi-singlesignon/pull/93) by [@eduardomozart](https://github.com/eduardomozart))*
- [`8c63d47`](https://github.com/edgardmessias/glpi-singlesignon/commit/8c63d47f64f5ab190b39ce39ecf0743ad5d23e05) - Add missing translation strings *(PR [#102](https://github.com/edgardmessias/glpi-singlesignon/pull/102) by [@eduardomozart](https://github.com/eduardomozart))*
- [`1a1a7e8`](https://github.com/edgardmessias/glpi-singlesignon/commit/1a1a7e8c7a4f8786bfdbc275b09efeae63cf3cc1) - Add API token date *(PR [#100](https://github.com/edgardmessias/glpi-singlesignon/pull/100) by [@eduardomozart](https://github.com/eduardomozart))*
- [`6a2be8e`](https://github.com/edgardmessias/glpi-singlesignon/commit/6a2be8e9e7116513ebc640c634b4a36dc5bb5306) - Add support for "Extra Options" field *(commit by [@eduardomozart](https://github.com/eduardomozart))*

### :wrench: Chores
- [`bd01b9c`](https://github.com/edgardmessias/glpi-singlesignon/commit/bd01b9c045b7dec749f51ccd608e0badbdd22fe7) - Updated vscode configs *(commit by [@edgardmessias](https://github.com/edgardmessias))*

[v1.4.0]: https://github.com/edgardmessias/glpi-singlesignon/compare/v1.3.3...v1.4.0
[v1.5.1]: https://github.com/edgardmessias/glpi-singlesignon/compare/v1.4.0...v1.5.1
