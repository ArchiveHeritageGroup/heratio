# BagIt packaging (RFC 8493) in Heratio

**Summary.** BagIt, standardised as IETF RFC 8493, is a simple, widely adopted
file-packaging format for safely moving and storing a set of files together with
their checksums. A "bag" is a directory with a fixed layout: a `bagit.txt`
declaration, a `bag-info.txt` of human-readable metadata, one or more
`manifest-<alg>.txt` files listing a checksum for every payload file, optional
`tagmanifest-<alg>.txt` files protecting the tag files themselves, and a `data/`
directory holding the actual payload. Because every file is checksummed inside
the bag, a recipient can verify nothing was lost or corrupted in transit. Heratio
uses BagIt as the on-disk format for its OAIS SIP / AIP / DIP packages and can
both build and validate RFC-8493-compliant bags.

## The concept

A valid BagIt v1.0 bag looks like:

```
<bag-name>/
  bagit.txt              BagIt-Version: 1.0 + Tag-File-Character-Encoding: UTF-8
  bag-info.txt           Source-Organization, External-Identifier, Bagging-Date,
                         Bag-Size, Payload-Oxum, ...
  manifest-sha256.txt    one checksum row per file under data/
  tagmanifest-sha256.txt checksums of bagit.txt + bag-info.txt + manifest-*.txt
  data/                  the payload tree
    ...
```

Validation re-hashes every payload file and compares against the manifest; if any
digest differs, or the Payload-Oxum (octet + file count) does not match, the bag
is invalid. BagIt is format-agnostic and tool-agnostic, which is why it is the
common interchange format between preservation systems.

## How Heratio addresses this

- **Builder + validator.** `AhgPreservation\Services\BagItService` builds
  RFC-8493-compliant BagIt v1.0 bags from the `digital_object` subtree of an
  information object, and validates existing bags. It writes the canonical
  structure (`bagit.txt`, `bag-info.txt`, `manifest-<alg>.txt`,
  `tagmanifest-<alg>.txt`, `data/`), records `Payload-Oxum` and a
  `Heratio-Information-Object-Id` in `bag-info.txt`, and can zip the result.
  Supported manifest algorithms include sha256 (default), sha512, and md5.
- **Admin actions.** Build a bag for a record with
  `POST /admin/preservation/package/build/{ioId}` (route
  `preservation.package.build`); validate an existing package with
  `POST /admin/preservation/package/{id}/validate` (route
  `preservation.package.validate`).
- **BagIt is the package format for OAIS.** The OAIS package builder
  (`AhgIngest\Services\OaisPackagerService`) and the OAIS lifecycle service
  (`AhgPreservation\Services\OaisLifecycleService`) both use BagIt as the
  underlying container for SIP / AIP / DIP, so SIP/AIP/DIP packages are bags on
  disk. See `dp-02-sip-aip-dip-lifecycle`.
- **Persistence.** Each bag is recorded as a `preservation_package` row
  (`format=bagit`, with the manifest algorithm), one `preservation_package_object`
  row per file (relative path + checksum), and `preservation_package_event` rows
  for every lifecycle PREMIS event.

## Gaps / not yet

- Heratio builds and validates BagIt v1.0; it does not implement the older BagIt
  "fetch.txt" hold-incomplete model (bags reference all their payload locally).
- Bag *import* (accepting an externally produced bag, validating it, and ingesting
  its payload as a SIP) is less developed than bag *export*; the primary,
  well-supported flow is Heratio building bags from its own records.
