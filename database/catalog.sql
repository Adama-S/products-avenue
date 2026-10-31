-- =============================================================================
-- catalog.sql: customer reviews of the Products Avenue catalogue
-- Engine: MySQL 8.0.16+ (CHECK constraints are enforced from this version)
-- Run:    mysql -u root -p catalog < catalog.sql
--         (Docker loads it into catalog and catalog_test on the first start)
-- Assumptions:
--   * products live in FakeStore API, not in this database: no foreign key on the
--     product, the application checks that the product exists before saving a review
--   * dates in UTC: the application connection sets time_zone = '+00:00'
--   * reviews are anonymous (no customer account), see the README
-- =============================================================================

SET NAMES utf8mb4;

DROP TABLE IF EXISTS comments;

CREATE TABLE comments (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    product_external_id INT UNSIGNED  NOT NULL,                   -- FakeStore product id: no foreign key possible
    author              VARCHAR(100)  NOT NULL,                   -- same limits as CommentValidator
    content             VARCHAR(1000) NOT NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NULL ON UPDATE CURRENT_TIMESTAMP, -- NULL until the first edit ("modifié")
    PRIMARY KEY (id),
    -- The only reads: reviews of a product newest first, and their count.
    -- InnoDB appends the primary key to the index, so ORDER BY created_at DESC, id DESC is served too.
    KEY idx_comments_product_created (product_external_id, created_at),
    CONSTRAINT chk_comments_author  CHECK (CHAR_LENGTH(TRIM(author)) > 0),
    CONSTRAINT chk_comments_content CHECK (CHAR_LENGTH(TRIM(content)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
