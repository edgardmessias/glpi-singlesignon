# Changelog

All notable changes to this project will be documented in this file. See [conventional commits](https://www.conventionalcommits.org/) for commit guidelines.

---
## [2.0.0-dev.4](https://github.com/edgardmessias/glpi-singlesignon/compare/v1.4.0..v2.0.0-dev.4) - 2026-04-03

### Bug Fixes

- Fixed test button - ([485bfda](https://github.com/edgardmessias/glpi-singlesignon/commit/485bfda8b82e989d6a404006ec17d806fd4ce71c)) - Edgard
- Fixed Azure login for users without picture (Closes #115) - ([20e2ba2](https://github.com/edgardmessias/glpi-singlesignon/commit/20e2ba26000c8420272bacf179efdd35cc7e290e)) - Edgard
- Fixed login for "Linked accounts" - ([bbc9770](https://github.com/edgardmessias/glpi-singlesignon/commit/bbc9770dfc9ed41f9e49aadc6480eeabb8f97689)) - Edgard
- Updated locales - ([f197fdb](https://github.com/edgardmessias/glpi-singlesignon/commit/f197fdbe36f151f23c1a5594d702d4382ab7e470)) - Edgard
- Fixed UI icons - ([cf629e6](https://github.com/edgardmessias/glpi-singlesignon/commit/cf629e6a5a2edfe2196f8d4e3f008337ac31a37e)) - Edgard
- Fixed public login images - ([bde7af0](https://github.com/edgardmessias/glpi-singlesignon/commit/bde7af04ac70ed35e06cd236f2fbd9949b714460)) - Edgard
- Fixed redirect issues (closes #157) - ([61f348b](https://github.com/edgardmessias/glpi-singlesignon/commit/61f348b5791d55fd4d917647b679e0f9ca93cf2d)) - Edgard

### Documentation

- update CHANGELOG.md for v1.4.0 [skip ci] - ([76e5e0a](https://github.com/edgardmessias/glpi-singlesignon/commit/76e5e0a983c60c15ebc600ce6d7decf589f2333e)) - eduardomozart
- update CHANGELOG.md for v1.5.1 [skip ci] - ([424485c](https://github.com/edgardmessias/glpi-singlesignon/commit/424485cfb5515340fa0f15494667caccefcd6d57)) - Killian-Aidalinfo

### Features

- **(provider)** add dynamic OAuth field mappings via JSONPath - ([33da2d5](https://github.com/edgardmessias/glpi-singlesignon/commit/33da2d5e3d17c23d30a91042fe2c13107cf1d451)) - Edgard
- **(ui)** Improved visual of link account in preferences page - ([099b98e](https://github.com/edgardmessias/glpi-singlesignon/commit/099b98e5b8d28dae4a53c3fca32c1f86773d23ba)) - Edgard
- **(ui)** Improved visual of provider config - ([2b4bd32](https://github.com/edgardmessias/glpi-singlesignon/commit/2b4bd320007266fe3376e18eacecbc5f8ea3910d)) - Edgard
- Improved login screen - ([8bbe597](https://github.com/edgardmessias/glpi-singlesignon/commit/8bbe5974174653ef8f9277cae72e04b83ff34393)) - Edgard
- Redesign login screen with a complete new UI - ([67a5305](https://github.com/edgardmessias/glpi-singlesignon/commit/67a5305f7c398bac80988dce9f9456c74a8954c9)) - Edgard
- Allow OAuth photo sync with avatar_url mapping for all providers - ([e0efb20](https://github.com/edgardmessias/glpi-singlesignon/commit/e0efb20967e455dcfd5b9ed79db5345367f56c4c)) - Edgard
- Add configurable photo sync modes and custom header options - ([093cdbc](https://github.com/edgardmessias/glpi-singlesignon/commit/093cdbc76d8eb3ea55e330c4aa2673e3f50fa4ff)) - Edgard
- new auto-registration flow for OAuth SSO users - ([09627a2](https://github.com/edgardmessias/glpi-singlesignon/commit/09627a227ab566b0f5da9a59be9522fa0cba41b3)) - Edgard

### Miscellaneous Chores

- **(ci)** streamline release and locale update workflows with git-cliff - ([4e56537](https://github.com/edgardmessias/glpi-singlesignon/commit/4e56537bfc94e0b573c9ba1e08884d84bcac36a6)) - Edgard
- **(locale)** Updated locales - ([0556a7a](https://github.com/edgardmessias/glpi-singlesignon/commit/0556a7a8c3a795be40e216fc9bfefeda1b146cec)) - Edgard
- Fixed PHP-CS-Fixer issues - ([954e466](https://github.com/edgardmessias/glpi-singlesignon/commit/954e466485520bb13a1ce4f60babf4da99f8c3cc)) - Edgard
- Fixed PHPStan issues - ([ec0fd73](https://github.com/edgardmessias/glpi-singlesignon/commit/ec0fd736d3976e3a04fb804e0e796c245eab4218)) - Edgard
- Fixed PHPStan "exit" issues - ([8d00e7b](https://github.com/edgardmessias/glpi-singlesignon/commit/8d00e7b8bbe093aefed6d610d1658c77b9920ae0)) - Edgard
- Removed old classes for GLPI < 10 - ([e1ec973](https://github.com/edgardmessias/glpi-singlesignon/commit/e1ec973512e88ded290f9f17ff2d20b534c003ee)) - Edgard
- Fixed copyright header - ([011322b](https://github.com/edgardmessias/glpi-singlesignon/commit/011322b0deaf81691a423e3662f5bdad0a33d3d2)) - Edgard
- Fixed Rector issues - ([3d1df02](https://github.com/edgardmessias/glpi-singlesignon/commit/3d1df0265e0bdee9c65a133bb031895d95b3f1db)) - Edgard
- Fixed Psalm issues - ([331b1e9](https://github.com/edgardmessias/glpi-singlesignon/commit/331b1e9255322ee43287460b739864c41112d5e8)) - Edgard
- Renamed branch master to main - ([7c368ee](https://github.com/edgardmessias/glpi-singlesignon/commit/7c368ee11d4b7a686307482ab45cf89bb206ce3c)) - Edgard
- Updated README.md - ([93f97ce](https://github.com/edgardmessias/glpi-singlesignon/commit/93f97ce06479c9f3dc6cda9fba9ef576aa92a78f)) - Edgard

### Refactoring

- **(ui)** migrate provider, preference and login UI to Twig - ([b1619cf](https://github.com/edgardmessias/glpi-singlesignon/commit/b1619cf660aa01b1ab9f70565a9c0d58a3c5c572)) - Edgard

### Ci

- Updated workflows - ([4ef516d](https://github.com/edgardmessias/glpi-singlesignon/commit/4ef516d7ffcdda89b2bbe97803d27b8058c647b4)) - Edgard

### I18n

- complete Spanish translations (es_ES 100%, add es_CO) (#159) - ([d28883b](https://github.com/edgardmessias/glpi-singlesignon/commit/d28883bad34ae68cdd2dde3da09989893da40362)) - Esteban Esquivel

---
## [1.4.0](https://github.com/edgardmessias/glpi-singlesignon/compare/v1.3.3..v1.4.0) - 2025-02-04

### Bug Fixes

- Fixed redirect for default provider - ([c7b1be1](https://github.com/edgardmessias/glpi-singlesignon/commit/c7b1be17b3c2d80c6df31d334d3c7d3de1214a74)) - Edgard
- Fixed show buttons for GLPI >= 10.0 - ([be22981](https://github.com/edgardmessias/glpi-singlesignon/commit/be229815a7f026e40903c588dc1c1eb3d7fef972)) - Edgard
- Add missing string to translation (#93) - ([ddc9c2d](https://github.com/edgardmessias/glpi-singlesignon/commit/ddc9c2ddc1a3dd7fe1f76c44c844b1d3e690c9d7)) - Eduardo Mozart de Oliveira
- Add missing translation strings (#102) - ([8c63d47](https://github.com/edgardmessias/glpi-singlesignon/commit/8c63d47f64f5ab190b39ce39ecf0743ad5d23e05)) - Eduardo Mozart de Oliveira
- Add API token date (#100) - ([1a1a7e8](https://github.com/edgardmessias/glpi-singlesignon/commit/1a1a7e8c7a4f8786bfdbc275b09efeae63cf3cc1)) - Eduardo Mozart de Oliveira

### Features

- **(locales)** french translation (#65) - ([51999b4](https://github.com/edgardmessias/glpi-singlesignon/commit/51999b40dfe7fafa9473755820acdd60cdc808fe)) - Mehdi
- Added es_ES translation - ([4901caf](https://github.com/edgardmessias/glpi-singlesignon/commit/4901cafe3c5d3eb0f7f41e93bff4a50912c166ae)) - Edgard
- Added lint to provider page from plugin page - ([7552525](https://github.com/edgardmessias/glpi-singlesignon/commit/755252547056d650a3e5a4c394c2b6af865e4ede)) - Edgard
- Added license (close #36) - ([135975f](https://github.com/edgardmessias/glpi-singlesignon/commit/135975fa192032871d5bbb10e13d300a05884506)) - Edgard
- Added create new user for Google (#70) - ([cae95d8](https://github.com/edgardmessias/glpi-singlesignon/commit/cae95d8a43c9c375368c10b46c640926b690e5df)) - ch-tm
- Improve Reverse Proxy and Plugin folder support (#103) - ([144c3cd](https://github.com/edgardmessias/glpi-singlesignon/commit/144c3cdc9c4d7915c64b21e8b64f56c522b40d90)) - Eduardo Mozart de Oliveira
- Sync GLPI photo with Azure AD (#101) - ([ebff864](https://github.com/edgardmessias/glpi-singlesignon/commit/ebff8646f933038ad94969cc1a424135381f9f9f)) - Eduardo Mozart de Oliveira
- Automatically close stale issues - ([d45b759](https://github.com/edgardmessias/glpi-singlesignon/commit/d45b759bc760ca1314f070796dd8cf1d51fc1368)) - Eduardo Mozart de Oliveira
- Automatically close stale issues - ([cc09ff3](https://github.com/edgardmessias/glpi-singlesignon/commit/cc09ff3e9be37856e11e1f1a5c58a2a673ef0aa4)) - Eduardo Mozart de Oliveira
- Add 'debug' tab to copy SSO provider info - ([fb7eae6](https://github.com/edgardmessias/glpi-singlesignon/commit/fb7eae6ebeb95de90613ed7167995045b6f9c6dd)) - Eduardo Mozart de Oliveira

### Fix

- Could not add picture see #88 (#89) - ([2c83a54](https://github.com/edgardmessias/glpi-singlesignon/commit/2c83a54f013e596d28b7d52beb56145d2555f4b0)) - Marcel
- Add support for "Extra Options" field - ([6a2be8e](https://github.com/edgardmessias/glpi-singlesignon/commit/6a2be8e9e7116513ebc640c634b4a36dc5bb5306)) - Eduardo Mozart de Oliveira

### Miscellaneous Chores

- Updated vscode configs - ([bd01b9c](https://github.com/edgardmessias/glpi-singlesignon/commit/bd01b9c045b7dec749f51ccd608e0badbdd22fe7)) - Edgard

### Build

- auto releases and auto changelog - ([a319105](https://github.com/edgardmessias/glpi-singlesignon/commit/a3191057ebd79b561172d8029ed3fb684c5ac7f2)) - Eduardo Mozart de Oliveira

---
## [1.3.3](https://github.com/edgardmessias/glpi-singlesignon/compare/v1.3.1..v1.3.3) - 2022-06-29

### Bug Fixes

- Added verbose debug information - ([3ba5059](https://github.com/edgardmessias/glpi-singlesignon/commit/3ba5059245a6b187890946d5bf93b46012eba0b3)) - Edgard

### Features

- New release - ([4a8fedc](https://github.com/edgardmessias/glpi-singlesignon/commit/4a8fedc486162e2d046937dad016d8aa33919e50)) - Edgard

### Miscellaneous Chores

- Removed funding - ([b59ac01](https://github.com/edgardmessias/glpi-singlesignon/commit/b59ac01f826f6fec3598c9418b215bd1ed3871d9)) - Edgard Lorraine Messias

### Style

- Fixed lint - ([dfe9bb7](https://github.com/edgardmessias/glpi-singlesignon/commit/dfe9bb78bd26bc6b9312174d47aaa7e1ce4c8477)) - Edgard

---
## [1.2.0](https://github.com/edgardmessias/glpi-singlesignon/compare/1.1.0..v1.2.0) - 2021-01-12

### Bug Fixes

- Fixed default scope - ([25f8fd6](https://github.com/edgardmessias/glpi-singlesignon/commit/25f8fd619526c1e28e47a7cb5cc979306f3eaf66)) - Edgard Messias
- Fixed http_build_query warning (close #3) - ([8854cc5](https://github.com/edgardmessias/glpi-singlesignon/commit/8854cc531295ebd104d16839ff70d90199b3db26)) - Edgard Messias

### Features

- Added button for fast test - ([92b722a](https://github.com/edgardmessias/glpi-singlesignon/commit/92b722af0e9d8a40fcb89a0aae55b4430e02f195)) - Edgard Messias

### Miscellaneous Chores

- Removed composer dependency - ([23c7b58](https://github.com/edgardmessias/glpi-singlesignon/commit/23c7b58e8dab970e24e4ebdc0d12869d12aa7532)) - Edgard Messias
- Show CallBack URL in form - ([7bf863e](https://github.com/edgardmessias/glpi-singlesignon/commit/7bf863e047e114cc9b0664f6334593e6a948a37b)) - Edgard Messias

---
## [1.0.0] - 2019-04-29

<!-- generated by git-cliff -->
