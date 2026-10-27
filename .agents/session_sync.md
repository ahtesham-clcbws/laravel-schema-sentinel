# Session Sync

## Active State
- Branch: `development`
- Target: v2.2.0 Release Closure
- Status: Release closure checks completed. 53 tests passing (149 assertions), 0 failures.

## Handoff Summary
- PostgreSQL cast handling refined: distinguished literal casts from SQL expression casts.
- Protected `now()::timestamp` and `CURRENT_TIMESTAMP::date` with `->useCurrent()`, rejected raw expression casts (`gen_random_uuid()::text`) with descriptive exceptions.
- Added focused regression tests in `MigrationGeneratorTest`.
- Synchronized documentation, test matrix, and results.
- Ready for final commit and push to `development`.
