# PHP 5.6.40 security backport source

This directory is the full PHP 5.6.40 source with the project's security backports applied.
Clone the monorepo and work directly in this directory; no separate download or patch step is
needed. One source tree builds both Windows x86 variants: TS (thread safe) and NTS (non thread
safe). They are separate configure/build variants and are tested separately.

- Project patch revision: `php5-windows-backports-5.6.40-r4`
- Target: Windows x86 (32-bit) only. PHP 7+, x64, and other platforms are out of scope.

## Provenance

Official upstream archive: [php-5.6.40.tar.xz](https://museum.php.net/php5/php-5.6.40.tar.xz)

```text
SHA-256 1369a51eee3995d7fbd1c5342e5cc917760e276d561595b6052b21ace2656d1c
```

The source is built up from two recorded patch layers on top of that archive:

1. `php-5.6.40-base1` – the adopted EOL mitigation set (unchanged combined patch, SHA-256
   `b70a18035c2ee11337d9712ec81cabc6f144b8959129a8b728530c31895342fa`). Its main input is the
   Sury PHP 5.6.40-104 patch `0261-Avoid-state-corruption-via-bailout.patch`, referencing
   [CVE-2026-109618 / GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
   and [CVE-2026-109619 / GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).
   Original attribution: Arnaud Le Blanc; co-authored by Ilija Tovilo. The combined patch also
   carries multipart body-part limits and VC11 supplements, and modifies 13 files.
2. `r2`–`r4` – the cumulative security backports and hardening set for this revision
   (patch SHA-256 `643a8ad1f5472fbbcd7a00a1f45357ac013c998bb40acce1af7e74f6b4c617d6`, 199
   source and test files changed relative to the base1 tree). It applies the reviewed Sury
   PHP 5.6.40-104 donor series (104 source/test patches, `NEWS` hunks excluded; captured archive
   SHA-256 `239d3fbf5b94d0e74a1b7f80874b38c62b756a28e6d69b2443f67500417d255b`), selected logic
   from Sury 5.6.40-105, and the project patch groups below.

   Sury 5.6.40-105 comparison: the multipart body-part cap (`0263`) is already present through
   the base1 supplement and is deliberately not re-applied; only the file-upload warning
   collapsing logic (`0262`, upstream commit `e45850c195`) is ported as a project fix in `r4`.
   The `NEWS` edit (`0264`) is replaced by this document. The `0261` change is a header comment
   only. The whole Sury `-105` series is therefore **not** imported verbatim.

Retain the PHP license and all original PHP, Zend, component, and patch attribution notices when
distributing derived source. The upstream license copy is in `LICENSE`.

## Included patch groups

Verified identifiers are named where the group's regression tests assert them. Groups without a
public identifier are recorded as review hardening for the components listed.

- **Request lifecycle (base1)** – CVE-2026-109618, CVE-2026-109619: allocator OOM and guarded
  overflow paths terminate the worker before PHP callbacks; affected request globals are reset;
  core session upload progress is disabled even when its INI says On. Multipart body-part limits
  count ignored parts and derive the default from input and upload limits; this is the multipart
  part cap referenced by CVE-2023-0662 (see the multipart notes below for the verified scope).
- **Multipart limits (r4)** – warning collapsing for the `max_file_uploads` overflow (Sury
  `0262`, upstream `e45850c195`): three excess file parts produce one warning instead of one
  warning per part. Normal upload behaviour is unchanged.
- **Phar TAR** – CVE-2026-6103, CVE-2026-7260: numeric/size/type checks and cyclic or invalid
  link handling across read, seek, copy, conversion, and write callers. PHP 8 lazy Phar metadata
  behavior is deliberately not backported because it changes legacy API behavior.
- **SOAP** – GHSA-hmxp-6pc4-f3vv, GHSA-m33r-qmcv-p97q: decoder ownership and reference-map
  lifetime fixes, bounded conversion depth, iterative XML cleanup/search, response body size
  checks, and a 16-byte cryptographic Digest nonce.
- **Windows device paths** – CVE-2026-17545: qualified reserved device components are refused
  before CRT file I/O. Bare device names (`NUL`, `CON`, ...) and the DOS device namespace keep
  their documented compatibility behavior.
- **mysqlnd** – bounded fixed-width and length-encoded packet reads, greeting/auth/OK/EOF/prepare
  packet bounds, metadata default bounds, authentication string bounds, text/binary row
  prevalidation, and result-metadata cleanup ownership. The fixed metadata block marker (0x0c)
  is verified; a wrong marker rejects the packet before the fixed block is parsed. Trailing
  bytes after the last column value in a row packet are rejected before any field codec runs.
  A partially decoded metadata field never keeps borrowed packet-buffer pointers, field slots
  are cleared before each parse and on failure, and a metadata failure releases the result
  through one persistence-aware cleanup path.
- **HTTP streams** – redirects compare scheme, host, and effective port; `Authorization`,
  `Cookie`, and `Proxy-Authorization` (including duplicates and folded continuations) are removed
  from the private header copy after a cross-origin redirect, with sticky removal across redirect
  chains. The original stream context stays unchanged and reusable. Proxy credentials are not
  restored into a cross-destination CONNECT. Related control: GHSA-c5f2-jwm7-mmq2.
- **TLS identity** – wildcard subject prefix and suffix lengths are bounded before matching, and
  subject alternative names take precedence over common-name fallback.
- **Convert filters** – binary line-break byte lengths are preserved in filter constructors
  (`pestrndup` with the stored length).
- **OpenSSL** – cipher output allocation accounts for wrapping expansion (WRAP-PAD) and checks
  signed limits before allocation on encrypt and decrypt.
- **Image metadata** – `getimagesize` publishes APPn segment bytes only after the complete
  segment has been read; a positive short read at EOF cannot publish a truncated segment.
- **URL** – the percent decoder passes high escape bytes to `ctype` in the unsigned-char range.
- **Review fixes (r2)** – IMAP MIME parameter/disposition key and value validation against header
  injection, hexadecimal acceptance in URL userinfo percent encoding, and a two-byte bound for
  the EXIF thumbnail JPEG length field.

## Behavior changes and compatibility notes

- OOM and guarded overflow paths terminate the process with `_exit(1)` before PHP callbacks.
  This is the adopted minimal EOL behavior, not the modern delayed-OOM design.
- Core session upload progress is unavailable even when `session.upload_progress.enabled` is On.
- Multipart parsing counts ignored MIME parts toward the body-part limit. The limit comes from
  `max_multipart_body_parts`; a value of `-1` derives the limit as `max_input_vars` +
  `max_file_uploads` (1020 with the default 1000 + 20) and does not mean "unlimited". Parsing
  stops at the first part over the limit with a single warning. The check happens after that
  part's headers are read, so this bounds part counts, not every multipart denial-of-service
  shape. This is the part cap behind CVE-2023-0662; the covered shapes are the ones in
  `tests/basic/multipart_limits.phpt`.
- Exceeding `max_file_uploads` now warns once per request instead of once per skipped file part.
- Malformed mysqlnd protocol data (greeting, auth, OK, EOF, prepare, metadata, rows) is rejected
  with warnings before any codec or callback runs. A wrong fixed-metadata-block marker or
  trailing bytes after the last column in a row are treated as malformed. Applications that
  relied on parsing partial server data will now see query failures instead of silent garbage.
  Protocol-compliant servers are unaffected (verified against MySQL 5.7.44).
- Cross-origin HTTP redirects no longer carry credential headers; same-origin redirects keep
  them. Stream contexts are not mutated.
- TLS peer verification no longer falls back to the common name when a subject alternative name
  is present, and wildcard labels are bounded.
- Reserved Windows device names with qualifiers are refused: a device component anywhere in a
  path (for example a directory named after a reserved device) is rejected before file access.
  Bare `NUL`/`CON`/... names still open the devices, matching documented Windows behavior.
- `imap_mail_compose` rejects header-injection characters in MIME parameter and disposition
  keys and values; previously such input could reach the composed header.
- `getimagesize` APPn results are complete or absent; they are never partially published.

## Extension profile

Built in (static):

```text
bcmath calendar com_dotnet ctype date dom ereg filter ftp hash iconv json libxml mcrypt
mysqlnd odbc pcre PDO Phar Reflection session SimpleXML SPL standard tokenizer wddx xml
xmlreader xmlwriter zip zlib
```

Shared DLLs:

```text
bz2 curl fileinfo gd gettext gmp imap intl ldap mbstring exif mysql mysqli openssl
pdo_mysql pdo_odbc pdo_pgsql pdo_sqlite pgsql shmop soap sockets sqlite3 tidy xmlrpc xsl
```

Zend extension: `opcache`. SAPIs: `cli`, `cgi` (FastCGI capable).

Oracle OCI and PDO Firebird are not in the profile because their client SDKs are not part of
the selected x86 dependency set. Apache handlers are not built in this profile; build with the
Apache handler configure options and use the TS variant for `apache2handler`/mod_php. Use NTS
for FastCGI/`cgi-fcgi` deployments such as IIS or nginx.

## Building

Toolchain requirements (x86, 32-bit tools):

- Microsoft Visual Studio C++ 2012 (VC11) compiler, version 17.00.50727.1
- Windows SDK 8
- PHP SDK build tools (bison, flex, re2c via the PHP SDK binary tools)
- The x86 dependency build set for PHP 5.6 VC11 (as used by the official Windows binaries)

The CRT is the VC11 dynamic runtime (`/MD`, MSVCR110). The build is not profile-guided.

Configure (identical for both variants except the thread-safety switch):

```text
configure.js --without-analyzer --with-mp=4 --enable-cli --enable-cgi --disable-phpdbg
  --disable-phpdbgs --disable-embed --disable-apache2handler --disable-apache2-2handler
  --disable-apache2-4handler --with-curl=shared --with-gd=shared --with-gettext=shared
  --with-mysql=shared,mysqlnd --with-mysqli=shared,mysqlnd --enable-pdo
  --with-pdo-mysql=shared,mysqlnd --with-pdo-sqlite=shared --with-sqlite3=shared
  --with-pgsql=shared --with-pdo-pgsql=shared --with-pdo-odbc=shared --enable-mbstring=shared
  --enable-mbregex --with-openssl=shared --enable-soap=shared --with-xmlrpc=shared
  --with-bz2=shared --with-gmp=shared --enable-intl=shared --with-ldap=shared
  --with-imap=shared --with-mcrypt=shared --with-tidy=shared --with-xsl=shared
  --enable-sockets=shared --enable-shmop=shared --enable-fileinfo=shared
  --enable-exif=shared --enable-opcache --enable-zip
  --with-php-build=<x86 dependency directory> <thread-safety switch>
```

The only variant difference is the thread-safety switch:

| Variant | Switch | PHP core DLL | Build ID |
| --- | --- | --- | --- |
| TS | `--enable-zts` | `php5ts.dll` | `API20131226,TS,VC11` |
| NTS | `--disable-zts` | `php5.dll` | `API20131226,NTS,VC11` |

Then run `nmake` (VC11, x86 tools). Keep both variants on separate output directories; they
share this one source tree but are not interchangeable binaries.

## Validation results (r4, 2026-10-22)

Both variants were built from this source with the toolchain above and tested separately on
Windows x86. The x86 claim is runtime verified (`PHP_INT_SIZE === 4`).

Common smoke check per variant: PHP 5.6.40 CLI, `PHP_INT_SIZE=4`, all configured modules
loaded (Zend OPcache and the 26 shared DLLs listed above), PDO SQLite, SQLite3, GD, mbregex,
GMP, Intl, OpenSSL and cURL controls passed.

| Check | TS (x86) | NTS (x86) |
| --- | --- | --- |
| ZTS flag | `PHP_ZTS=1` | `PHP_ZTS=0` |
| Focused security/control suite (32 tests) | 31 PASS, 1 SKIP, 0 FAIL | 31 PASS, 1 SKIP, 0 FAIL |
| All changed PHPTs (98 tests) | 83 PASS, 15 SKIP, 0 FAIL | 83 PASS, 15 SKIP, 0 FAIL |
| mysqlnd loopback peer (pinned case counts) | packets 201/201, metadata 45/45, rows 184/184, auth-long 14/14, 0 FAIL | same, 0 FAIL |
| mysqlnd loopback peer, procedural `mysqli_connect()` (nonpersistent handle) | metadata 45/45, rows 184/184, 0 FAIL | same, 0 FAIL |
| Multipart limits PHP test (CGI) | 5/5 cases, 0 FAIL | 5/5 cases, 0 FAIL |
| Real MySQL 5.7.44 compatibility | 11/11 PASS | 11/11 PASS |
| Real MariaDB 10.11.19 compatibility | 11/11 PASS | 11/11 PASS |
| FastCGI persistent worker | 15 PASS, 0 FAIL | 15 PASS, 0 FAIL |

The focused suite covers the patch groups above: SOAP security decoders, Phar TAR CVEs,
mysqlnd packet/metadata/row/auth bounds, HTTP redirect credential handling, OpenSSL cipher
output and peer identity, convert-filter binary line breaks, JPEG APP segments, Windows device
paths, URL percent bytes, LDAP escape controls, and the IMAP/EXIF/filter review fixes.

Observed dependency versions in the tested binaries: OpenSSL 1.0.2q (20 Nov 2018), libxml
2.9.4, cURL 7.59.0, ICU 57.1, SQLite 3.8.10.2 (bundled).

### Skipped and unavailable checks

Skips are recorded as unavailable runtime paths, not as passes:

- OpenSSL WRAP-PAD cipher capacity test: WRAP-PAD is unavailable in the linked OpenSSL 1.0.2q,
  so the WRAP-PAD expansion path is unverified on this dependency version.
- LDAP multi-gigabyte `ldap_escape` boundary tests: gated behind an explicit large-test
  environment flag and not run in the ordinary x86 pass.
- PDO Firebird tests (5): the extension is not built (no Firebird client SDK in the dependency
  set).
- Two tests need Windows facilities not available in the test environment: a symlink-privilege
  Phar test and a symlink name-length test.
- Remaining skips are tests marked by their SKIPIF as not applicable to Windows, requiring a
  live PostgreSQL server, or explicitly gated as slow tests.

### Validation levels

The claims above separate as follows:

| Level | Status |
| --- | --- |
| source implemented | yes (patch groups in this document) |
| built (x86 VC11 TS + NTS) | yes (this revision) |
| tested: CGI one-process-per-request | yes (PHPT suite plus CGI multipart limits) |
| tested: persistent FastCGI worker | yes (php-cgi.exe FastCGI mode, 15 checks per variant) |
| tested: real database | yes (MySQL 5.7.44, MariaDB 10.11.19; mysqli/mysqlnd query paths) |
| deployed (IIS / Apache / application) | no |

### Known limits

- Focused regression tests only; the full upstream PHP test suite was not run.
- Windows x86 only; no x64, ARM, or non-Windows results are claimed.
- No IIS/Apache deployment or application workflow testing was performed. FastCGI persistent
  worker behaviour was tested through php-cgi.exe in FastCGI mode: worker PID persistence,
  sessions, multipart uploads, body-part limit enforcement (exact accepted parts and warning
  counts), file-upload-limit warning collapsing, upload-progress suppression, oversized
  boundary rejection, and a controlled worker exit followed by a fresh worker continuing the
  session. The controlled exit terminates the worker process from the harness; it is not a
  real allocator-OOM termination of the base1 `_exit(1)` path.
- Real-server compatibility verified against MySQL 5.7.44 and MariaDB 10.11.19 (buffered,
  unbuffered, prepared statements, binary protocol rows, NULL/special values, round-trip).
  PHP 5.6 cannot connect to MySQL 8.x out of the box because MySQL 8 sends charset 255
  (utf8mb4_0900_ai_ci) in the handshake which is unknown to PHP 5.6; this is a pre-existing
  limitation not introduced by this patch. Configure MySQL 8 with a pre-8.0 default charset in
  my.cnf and restart to use PHP 5.6.
- External dependency versions are those listed; vulnerabilities inside those dependency
  versions are not claimed to be fixed by this source patch.
- Only the patch groups above carry project verification. The applied donor series is recorded
  patch by patch in the private project evidence, but individual donor fixes outside the tested
  groups are not separately re-verified.
- This is a community backport, not an official PHP release. Upstream baseline and project
  revision are recorded separately throughout.

## Historical base1 validation (recorded separately)

The adopted base1 tree carries its own earlier x86 TS / VC11 evidence (17/17 EOL CGI, 6/6
persistent FastCGI, 11/11 extension checks, 9/9 session tests) recorded on 2026-10-22. Those
results describe the base1 tree only. The r4 results above are the tests rerun against this
revision. A test and build attestation binding the source fingerprint, binary SHA-256 values,
commands and raw results is kept in the private project evidence.
