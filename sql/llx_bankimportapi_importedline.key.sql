-- Copyright (C) 2020 Florian DUFOURG <florian.dufourg@gmail.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


-- BEGIN MODULEBUILDER INDEXES
ALTER TABLE llx_bankimportapi_importedline ADD INDEX idx_bankimportapi_importedline_ref (ref);
ALTER TABLE llx_bankimportapi_importedline ADD CONSTRAINT llx_bankimportapi_importedline_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
ALTER TABLE llx_bankimportapi_importedline ADD INDEX idx_bankimportapi_importedline_fk_bank (fk_bank);
-- END MODULEBUILDER INDEXES

-- rowid is the primary key already
ALTER TABLE llx_bankimportapi_importedline DROP INDEX idx_bankimportapi_importedline_rowid;

-- A Qonto transaction can only be imported once on a bank account. run_sql() ignores the duplicate key error, so the
-- unique key would silently not be created while duplicates remain: the extra marks of a transaction imported twice
-- are renamed first (the first import keeps the mark), and stay in the table with the id of the duplicate element.
UPDATE llx_bankimportapi_importedline SET ref = CONCAT(ref, '-duplicate-', rowid) WHERE rowid NOT IN (SELECT keep FROM (SELECT MIN(rowid) AS keep FROM llx_bankimportapi_importedline GROUP BY fk_bank, ref) AS k);
ALTER TABLE llx_bankimportapi_importedline ADD UNIQUE INDEX uk_bankimportapi_importedline_fk_bank_ref (fk_bank, ref);

--ALTER TABLE llx_bankimportapi_importedline ADD UNIQUE INDEX uk_bankimportapi_importedline_fieldxy(fieldx, fieldy);

--ALTER TABLE llx_bankimportapi_importedline ADD CONSTRAINT llx_bankimportapi_importedline_fk_field FOREIGN KEY (fk_field) REFERENCES llx_bankimportapi_myotherobject(rowid);

