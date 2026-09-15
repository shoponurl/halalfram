-- Guideline ch. 6 Sprint 09 / ch. 8 launch gate: "the production DB user cannot DROP or ALTER".
--
-- Run ONCE as a MySQL admin, on the production server, after replacing both CHANGE_ME passwords with
-- long random values generated on that server (never typed into chat, a ticket, or an AI tool):
--
--   mysql -u root -p < deploy/mysql-production-grants.sql
--
-- Two accounts:
--   halal_app      — what the running site uses (.env DB_USERNAME). Read/write rows, nothing else.
--   halal_migrate  — used only by the deploy script (migrations), ops:backup and ops:restore-drill
--                    (.env DB_MIGRATE_USERNAME). Never used by a web request.
--
-- Afterwards `php artisan launch:check` confirms the app user's grants.

CREATE USER IF NOT EXISTS 'halal_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_APP_PASSWORD';
CREATE USER IF NOT EXISTS 'halal_migrate'@'localhost' IDENTIFIED BY 'CHANGE_ME_MIGRATE_PASSWORD';

-- SELECT ... FOR UPDATE (every stock/money row lock) needs SELECT plus UPDATE, which this covers.
GRANT SELECT, INSERT, UPDATE, DELETE ON halal_farm_store.* TO 'halal_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, LOCK TABLES, SHOW VIEW
    ON halal_farm_store.* TO 'halal_migrate'@'localhost';

-- The restore drill restores into this scratch database, never into the live one.
GRANT ALL PRIVILEGES ON halal_restore_drill.* TO 'halal_migrate'@'localhost';

FLUSH PRIVILEGES;
