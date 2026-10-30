
#!/bin/sh
# Runs once, on first start of an empty data volume.
# One database + one user per service; no service can read another's data.
set -e

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS concord CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'concord'@'%' IDENTIFIED BY '${CONCORD_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON concord.* TO 'concord'@'%';

CREATE DATABASE IF NOT EXISTS concord_iam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'iam'@'%' IDENTIFIED BY '${IAM_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON concord_iam.* TO 'iam'@'%';

CREATE DATABASE IF NOT EXISTS concord_hr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'hr'@'%' IDENTIFIED BY '${HR_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON concord_hr.* TO 'hr'@'%';

FLUSH PRIVILEGES;
SQL
