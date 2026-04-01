# Changelog

All notable changes to `codeitamarjr/laravel-attachments` should be documented in this file.

The format is based on Keep a Changelog and this package follows Semantic Versioning where practical.

## Unreleased

## 0.6.0 - 2026-04-02

- Added clearer collection access helpers: `singleAttachment()`, `lastAttachment()`, and `attachmentAt()`.
- Added explicit collection URL helpers: `firstAttachmentUrl()`, `lastAttachmentUrl()`, and `attachmentUrlAt()`.
- Removed the `attachmentUrl()` alias in favor of the explicit `firstAttachmentUrl()` helper.
- Expanded collection lifecycle coverage with more end-to-end tests and clearer README guidance.

## 0.5.0 - 2026-04-01

- Clarified multi-file collection semantics across the package API and README.
- Added `attachmentsFor()` and `firstAttachment()` collection helpers for clearer single-file and multi-file usage.
- Added `replaceById()` to replace one attachment inside a multi-file collection without clearing the rest.
- Added `deleteById()` to delete one attachment inside a multi-file collection without clearing the rest.
- Expanded the README with quick start, package motivation, default visibility notes, and more explicit collection guidance.
- Added PHPDoc across the public package API for better IDE help and package discoverability.
- Broadened end-to-end coverage for append, full-collection replace, targeted replace, full-collection delete, targeted delete, and cross-model safety scenarios.

## 0.4.0 - 2026-04-01

- Added package maintenance files and CI workflow.
- Added Laravel 13 and PHP 8.5 compatibility.
- Expanded the README with upgrade guidance, testing guidance, and invoice-based examples.
- Added broader end-to-end package coverage for upgrade and edge-case behavior.
- Improved package contracts and generalized uploader configuration for reusable host applications.

## 0.3.0 - 2026-04-01

- Added public/private attachment visibility support with URL abstraction.
- Added configurable uploader model and uploader foreign key support.
- Added an explicit `Attachable` contract for supported models.
- Added package-level Testbench coverage for lifecycle, upgrade, and edge-case behavior.
- Improved package documentation, upgrade notes, and contribution guidance.
