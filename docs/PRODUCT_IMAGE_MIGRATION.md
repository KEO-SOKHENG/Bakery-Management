# Product images: move to the media storage disk

## Problem (verified)

`ProductController` used to save uploads with `$file->move(public_path('uploads/products'))` and store `uploads/products/<file>` in `products.image_path`. That folder is on the **web server's own disk** (git-ignored, temporary on Render). It was never part of the media storage (`products/` prefix) that Full Backup covers, and files are lost on redeploy.

## What changed in the code

| Area | Change |
|---|---|
| New uploads | Stored on the media disk (Supabase in production, `public` disk locally) as `products/<file>`; `image_path` = `products/<file>`. |
| Existing rows | **Unchanged.** `uploads/products/<file>` still works: it is served from the legacy folder while that file exists, otherwise from `products/<file>` on the media disk. |
| Delete / replace | Removes the legacy file and/or the media object. |
| Display | `Product::getImageUrl()` → `ProductImageStorage::url()`. No storage round-trips for remote disks (no per-image HEAD requests). |
| Backup | `products/` is in the backed-up media scope; the reference check reports any referenced image that is missing from a backup. |

## Migration command (copy only)

```
php artisan products:migrate-images                 # dry run: shows what would be copied
php artisan products:migrate-images --apply         # copy + verify
php artisan products:migrate-images --rollback=<manifest-file>   # undo
```

Guarantees:

* **Copy, never move:** source files are never modified or deleted.
* **Database untouched:** no row is changed (no migration needed).
* **Checksums:** each copy is read back from the destination and compared (SHA-256). A mismatch removes the bad copy and fails the command.
* **No overwrite:** an existing destination object with different content is reported as a `conflict` and left alone; identical content is `already_present` (re-running is safe).
* **Manifest:** `storage/app/private/migrations/product-images-<time>-<id>.json` lists source, destination, size, SHA-256, whether a product references it, and any dangling references.

## Rollback

`--rollback=<manifest>` deletes only the destination objects that the manifest says were **copied** by that run, and only if their SHA-256 still matches. Objects that already existed are never touched. Source files and database rows were never modified, so after a rollback the application serves the legacy files exactly as before.

## Production plan (requires owner approval - NOT done)

1. **Before the next deploy**, run `--apply` inside the live container (the legacy files only exist there; a redeploy erases them). Confirm the output reports no `failed`/`conflict`.
2. Confirm by checking a few product pages and by running a Full Backup: `media.references.missing_count` must be 0 for `products/`.
3. Keep the manifest file. Later, after at least one verified Full Backup, the legacy folder can be left to expire on its own (do not delete it manually).
4. Rollback at any time with the command above.

Local verification (development only): 3 files copied, SHA-256 verified, rollback removed exactly those 3 copies, re-apply restored them, sources untouched.
