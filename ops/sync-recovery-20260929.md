# Synchronization recovery, September 29, 2026

## Fault and changes

The oldest orders job repeatedly reclaimed its parent without recovering eight expired running detail tasks. Its claim counter exceeded 1.48 million; this is a queue claim count, not an upstream request count. Other shops were starved.

`SyncQueue` now reclaims expired detail leases, skips parents whose remaining tasks are waiting for a retry or an active lease, and rotates eligible jobs by last update. Job/shop filters apply before claiming. Heartbeats extend only the claimed batch. The worker has a local process lock and bounded exponential backoff, including for legacy high attempt counts.

Ads synchronization now records partial snapshots and failed jobs when reports are unavailable, retaining successful metadata and previously available reports. Chat `user_is_forbidden` is a channel error and does not mark the entire shop expired. Access failures receive at least 15 minutes before the next scheduled attempt. Schedule initialization preserves operator intervals and paused modes; enqueueing does not erase previous errors.

## Runtime evidence

The worker was restarted with the recovery changes. Four existing chat snapshots incorrectly labeled expired for `user_is_forbidden` were corrected to error; normal shop health checks were requested.

At **07:30 WIB**:

| Original job | Shop | Result |
| --- | --- | --- |
| 22 | hiban.store | Completed; only one additional claim after recovery |
| 172 | Royal Abiya | Completed |
| 3857 | Haviel | Completed |
| 3869 | Hiban Signature | Running, advanced to page 12 |
| 4054 | Hermosa | Completed |
| 4068 | elfuad | Running, advanced to page 12 |
| 4083 | Safariana | Completed |

No running detail tasks had expired leases. All seven shops had new order-detail sync timestamps during the recovery observation. The two remaining original jobs were progressing; completion of their full reconciliation is not claimed here.

## Validation

- 26 synchronization checks passed in `tests/sync-recovery.php`, using temporary tables rather than production queue rows.
- 135 mapping checks passed in `tests/ads-performance.php`.
- 83 browser checks passed in `tests/ads-ui.cjs`.
- Changed PHP files passed syntax checks; `git diff --check` passed.
- A second worker invocation exited immediately while the active worker held its lock.

## Remaining access failures

Shopee ads reports still return `90309999` for the tested server-side session despite successful identity lookup. Four chat shops still return `user_is_forbidden`. Queue recovery does not resolve those upstream access decisions. Existing cookies alone have not been sufficient for the tested ads request. No browser security token was fabricated or copied into static application configuration.
