# Backup encryption keys: recovery runbook

## How backups are encrypted

* Each `.bkp` package is encrypted with libsodium `secretstream` (XChaCha20-Poly1305, 1 MB authenticated chunks).
* The 256-bit key is derived with HKDF-SHA256 from key material (see "Which key" below). The key itself is never stored in the package, history records, manifests or logs.
* Every package (format "BKPENC2") starts with a **key id**: the first 8 bytes of a hash of the derived key, shown as 16 hex characters (for example `b06d60699c09c0ca`). The id identifies a key; it does not reveal it. The same id is stored in `manifest.json` (`encryption_key_id`) and in the backup history record.
* Older packages ("BKPENC1", no key id) remain readable: the system tries every available key.

## Which key is used

| Purpose | Setting | Notes |
|---|---|---|
| Encrypt new backups | `BACKUP_ENCRYPTION_KEY` if set, otherwise `APP_KEY` | A dedicated backup key is recommended so rotating `APP_KEY` never affects backups. |
| Decrypt | the dedicated key, `APP_KEY`, Laravel `APP_PREVIOUS_KEYS`, and `BACKUP_PREVIOUS_ENCRYPTION_KEYS` (comma separated) | The key id in the header selects the right key. |

Generate a dedicated key: `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`

## What happens if a key is lost

A package can only be decrypted with the key that has its key id. If no configured key matches, verification stops with:
`No key available for this package (key id <id>)` and the package cannot be recovered. There is no backdoor. **Losing every copy of the key means the encrypted backups are permanently unreadable.**

## Required safeguards (owner action)

1. Store the backup key (and `APP_KEY`) in **two independent places**, for example the company password manager and a sealed offline copy, held by two different people.
2. Record the **key id** (not the key) next to each stored copy, so the right key can be found for any package.
3. Never put keys in tickets, chat, logs, or the repository.

## Rotating a key (safe procedure)

1. Run `php artisan backup:doctor` and note the current key id.
2. Add the **old** key to `BACKUP_PREVIOUS_ENCRYPTION_KEYS` (or leave it in `APP_PREVIOUS_KEYS`) *before* changing anything.
3. Set the new `BACKUP_ENCRYPTION_KEY` (or the new `APP_KEY`).
4. Run `php artisan backup:doctor`: it must list **2 keys** that can decrypt.
5. Create a new full backup and verify it (new key id appears in the history and manifest).
6. Verify one old backup still passes ("Verify Package" in the UI).
7. Only after step 6 may the old key be removed from the previous-keys list, and only once every package encrypted with it is either re-created or deliberately retired.

## Recovery drill (do this once a year)

On a clean machine with the application code and **only the stored key**: restore the key into `BACKUP_ENCRYPTION_KEY`, upload an old package in *Backup & Restore → Restore System → Verify Package*, and confirm every check passes. Record the date and the key id used.

## Recovering after `APP_KEY` was rotated without keeping the old key

1. Find the stored copy of the old key (password manager / sealed copy) using the package key id.
2. Put it in `BACKUP_PREVIOUS_ENCRYPTION_KEYS`.
3. Run `php artisan backup:doctor`, then verify the package again.
If no copy exists the package is unrecoverable; keep using the newest package made with a known key.

## Troubleshooting

| Message | Meaning |
|---|---|
| `No key available for this package (key id …)` | None of the configured keys matches. Restore the key with that id. |
| `Encrypted package failed authentication (tampered or wrong key)` | The file was modified or damaged (key id matched, authentication failed). Use another copy. |
| `Encrypted package is truncated / incomplete` | The upload or download was cut short. Re-download and retry. |
