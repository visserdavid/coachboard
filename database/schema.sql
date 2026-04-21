-- CoachBoard Database Schema
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- AUTHENTICATION
-- ============================================================

CREATE TABLE IF NOT EXISTS `user` (
    `id`               INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `first_name`       VARCHAR(100)      NOT NULL,
    `email`            VARCHAR(255)      NOT NULL,
    `is_administrator` TINYINT(1)        NOT NULL DEFAULT 0,
    `is_trainer`       TINYINT(1)        NOT NULL DEFAULT 0,
    `is_coach`         TINYINT(1)        NOT NULL DEFAULT 0,
    `is_assistant`     TINYINT(1)        NOT NULL DEFAULT 0,
    `active`           TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `magic_link` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED  NOT NULL,
    `token`      VARCHAR(255)  NOT NULL,
    `expires_at` TIMESTAMP     NOT NULL,
    `used_at`    TIMESTAMP     NULL DEFAULT NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_magic_link_token` (`token`),
    KEY `idx_magic_link_user_id` (`user_id`),
    CONSTRAINT `fk_magic_link_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- STRUCTURE
-- ============================================================

CREATE TABLE IF NOT EXISTS `season` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(50)   NOT NULL,
    `has_phases` TINYINT(1)    NOT NULL DEFAULT 0,
    `active`     TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP     NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `phase` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `season_id`  INT UNSIGNED  NOT NULL,
    -- number: 1, 2, or 3
    `number`     TINYINT       NOT NULL,
    `label`      VARCHAR(100)  NULL DEFAULT NULL,
    `focus`      TEXT          NULL DEFAULT NULL,
    `start_date` DATE          NOT NULL,
    `end_date`   DATE          NOT NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_phase_season_id` (`season_id`),
    CONSTRAINT `fk_phase_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `team` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `season_id`       INT UNSIGNED  NOT NULL,
    `name`            VARCHAR(100)  NOT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_team_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `team_training_day` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `team_id`     INT UNSIGNED     NOT NULL,
    -- day_of_week: 1=Monday through 7=Sunday
    `day_of_week` TINYINT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_team_training_day_team` (`team_id`),
    CONSTRAINT `fk_team_training_day_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- FORMATIONS
-- (before match, which references formation)
-- ============================================================

CREATE TABLE IF NOT EXISTS `formation` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(50)   NOT NULL,
    `outfield_players` TINYINT UNSIGNED NOT NULL DEFAULT 10,
    `is_default`      TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `formation_position` (
    `id`             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `formation_id`   INT UNSIGNED    NOT NULL,
    `position_label` VARCHAR(50)     NOT NULL,
    -- line: 'goalkeeper', 'defence', 'midfield', 'attack'
    `line`           VARCHAR(20)     NOT NULL,
    `pos_x`          DECIMAL(5,2)    NOT NULL,
    `pos_y`          DECIMAL(5,2)    NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_formation_position_formation` FOREIGN KEY (`formation_id`) REFERENCES `formation` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PLAYERS
-- ============================================================

CREATE TABLE IF NOT EXISTS `player` (
    `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `team_id`        INT UNSIGNED   NOT NULL,
    `first_name`     VARCHAR(100)   NOT NULL,
    `squad_number`   TINYINT UNSIGNED NULL DEFAULT NULL,
    -- preferred_foot: 'right', 'left'
    `preferred_foot` VARCHAR(10)    NULL DEFAULT NULL,
    -- preferred_line: 'goalkeeper', 'defence', 'midfield', 'attack'
    `preferred_line` VARCHAR(20)    NULL DEFAULT NULL,
    `photo_path`     VARCHAR(500)   NULL DEFAULT NULL,
    `photo_consent`  TINYINT(1)     NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     TIMESTAMP      NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_player_team_id` (`team_id`),
    KEY `idx_player_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_player_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `player_skill` (
    `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `player_id`    INT UNSIGNED    NOT NULL,
    `season_id`    INT UNSIGNED    NOT NULL,
    -- All skills: scale 1–5
    `pace`         TINYINT UNSIGNED NULL DEFAULT NULL,
    `shooting`     TINYINT UNSIGNED NULL DEFAULT NULL,
    `passing`      TINYINT UNSIGNED NULL DEFAULT NULL,
    `dribbling`    TINYINT UNSIGNED NULL DEFAULT NULL,
    `defending`    TINYINT UNSIGNED NULL DEFAULT NULL,
    `physicality`  TINYINT UNSIGNED NULL DEFAULT NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_player_skill_player_season` (`player_id`, `season_id`),
    CONSTRAINT `fk_player_skill_player` FOREIGN KEY (`player_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_player_skill_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TRAINING SESSIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS `training_session` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `team_id`    INT UNSIGNED  NOT NULL,
    `date`       DATE          NOT NULL,
    `cancelled`  TINYINT(1)    NOT NULL DEFAULT 0,
    `notes`      TEXT          NULL DEFAULT NULL,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_training_session_team_date` (`team_id`, `date`),
    CONSTRAINT `fk_training_session_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `training_focus` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `training_session_id` INT UNSIGNED  NOT NULL,
    -- focus: 'attacking', 'defending', 'transitioning'
    `focus`               VARCHAR(20)   NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_training_focus_session` FOREIGN KEY (`training_session_id`) REFERENCES `training_session` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ATTENDANCE (polymorphic: training_session or match)
-- ============================================================

CREATE TABLE IF NOT EXISTS `attendance` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `player_id`      INT UNSIGNED  NOT NULL,
    -- context_type: 'training_session' or 'match'
    `context_type`   VARCHAR(20)   NOT NULL,
    `context_id`     INT UNSIGNED  NOT NULL,
    -- status: 'present', 'absent', 'injured'
    `status`         VARCHAR(10)   NOT NULL,
    -- absence_reason: 'sick', 'holiday', 'school', 'other'
    `absence_reason` VARCHAR(10)   NULL DEFAULT NULL,
    `injury_note`    VARCHAR(255)  NULL DEFAULT NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attendance_context` (`context_type`, `context_id`),
    KEY `idx_attendance_player_id` (`player_id`),
    CONSTRAINT `fk_attendance_player` FOREIGN KEY (`player_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- MATCHES
-- ============================================================

CREATE TABLE IF NOT EXISTS `match` (
    `id`                    INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `team_id`               INT UNSIGNED    NOT NULL,
    `formation_id`          INT UNSIGNED    NULL DEFAULT NULL,
    `date`                  DATE            NOT NULL,
    `kick_off_time`         TIME            NULL DEFAULT NULL,
    `opponent`              VARCHAR(150)    NOT NULL,
    -- home_away: 'home', 'away'
    `home_away`             VARCHAR(5)      NOT NULL,
    -- match_type: 'league', 'tournament', 'friendly'
    `match_type`            VARCHAR(15)     NOT NULL,
    `half_duration_minutes` TINYINT UNSIGNED NOT NULL DEFAULT 45,
    `goals_scored`          TINYINT UNSIGNED NULL DEFAULT NULL,
    `goals_conceded`        TINYINT UNSIGNED NULL DEFAULT NULL,
    -- status: 'planned', 'prepared', 'active', 'finished'
    `status`                VARCHAR(10)     NOT NULL DEFAULT 'planned',
    `livestream_token`      VARCHAR(64)     NULL DEFAULT NULL,
    `created_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_match_livestream_token` (`livestream_token`),
    KEY `idx_match_team_date` (`team_id`, `date`),
    CONSTRAINT `fk_match_team` FOREIGN KEY (`team_id`) REFERENCES `team` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_formation` FOREIGN KEY (`formation_id`) REFERENCES `formation` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `match_player` (
    `id`                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `match_id`            INT UNSIGNED    NOT NULL,
    `player_id`           INT UNSIGNED    NULL DEFAULT NULL,
    `is_guest`            TINYINT(1)      NOT NULL DEFAULT 0,
    `guest_name`          VARCHAR(100)    NULL DEFAULT NULL,
    `guest_squad_number`  TINYINT UNSIGNED NULL DEFAULT NULL,
    `in_starting_eleven`  TINYINT(1)      NOT NULL DEFAULT 0,
    `position_label`      VARCHAR(50)     NULL DEFAULT NULL,
    `pos_x`               DECIMAL(5,2)    NULL DEFAULT NULL,
    `pos_y`               DECIMAL(5,2)    NULL DEFAULT NULL,
    `playing_time_seconds` INT UNSIGNED   NOT NULL DEFAULT 0,
    `created_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_match_player_match_player` (`match_id`, `player_id`),
    CONSTRAINT `fk_match_player_match` FOREIGN KEY (`match_id`) REFERENCES `match` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_player_player` FOREIGN KEY (`player_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `match_half` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `match_id`   INT UNSIGNED  NOT NULL,
    `number`     TINYINT UNSIGNED NOT NULL,
    `started_at` TIMESTAMP     NULL DEFAULT NULL,
    `stopped_at` TIMESTAMP     NULL DEFAULT NULL,
    `elapsed_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_match_half_match_number` (`match_id`, `number`),
    CONSTRAINT `fk_match_half_match` FOREIGN KEY (`match_id`) REFERENCES `match` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `match_event` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `match_id`        INT UNSIGNED  NOT NULL,
    `half`            TINYINT UNSIGNED NOT NULL,
    `minute`          TINYINT UNSIGNED NOT NULL,
    -- event_type: 'goal', 'own_goal', 'yellow_card', 'red_card', 'note'
    `event_type`      VARCHAR(15)   NOT NULL,
    `player_id`       INT UNSIGNED  NULL DEFAULT NULL,
    `match_player_id` INT UNSIGNED  NULL DEFAULT NULL,
    `assist_player_id` INT UNSIGNED NULL DEFAULT NULL,
    `assist_match_player_id` INT UNSIGNED NULL DEFAULT NULL,
    -- scored_via: 'open_play', 'free_kick', 'penalty'
    `scored_via`      VARCHAR(15)   NULL DEFAULT NULL,
    `penalty_scored`  TINYINT(1)    NULL DEFAULT NULL,
    -- zone: 'tl', 'tm', 'tr', 'ml', 'mm', 'mr', 'bl', 'bm', 'br'
    `zone`            VARCHAR(2)    NULL DEFAULT NULL,
    `note_text`       TEXT          NULL DEFAULT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_match_event_match_id` (`match_id`),
    CONSTRAINT `fk_match_event_match` FOREIGN KEY (`match_id`) REFERENCES `match` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_event_player` FOREIGN KEY (`player_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_event_match_player` FOREIGN KEY (`match_player_id`) REFERENCES `match_player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_event_assist_player` FOREIGN KEY (`assist_player_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_event_assist_match_player` FOREIGN KEY (`assist_match_player_id`) REFERENCES `match_player` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `substitution` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `match_id`      INT UNSIGNED    NOT NULL,
    `half`          TINYINT UNSIGNED NOT NULL,
    `minute`        TINYINT UNSIGNED NOT NULL,
    `player_off_id` INT UNSIGNED    NULL DEFAULT NULL,
    `player_off_match_player_id` INT UNSIGNED NOT NULL,
    `player_on_id`  INT UNSIGNED    NULL DEFAULT NULL,
    `player_on_match_player_id` INT UNSIGNED NOT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_substitution_match_id` (`match_id`),
    CONSTRAINT `fk_substitution_match` FOREIGN KEY (`match_id`) REFERENCES `match` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_substitution_player_off` FOREIGN KEY (`player_off_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_substitution_player_off_match_player` FOREIGN KEY (`player_off_match_player_id`) REFERENCES `match_player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_substitution_player_on` FOREIGN KEY (`player_on_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_substitution_player_on_match_player` FOREIGN KEY (`player_on_match_player_id`) REFERENCES `match_player` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- POST-MATCH RATINGS
-- ============================================================

CREATE TABLE IF NOT EXISTS `match_rating` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `match_id`    INT UNSIGNED    NOT NULL,
    `player_id`   INT UNSIGNED    NOT NULL,
    -- All skills: scale 1–5
    `pace`        TINYINT UNSIGNED NULL DEFAULT NULL,
    `shooting`    TINYINT UNSIGNED NULL DEFAULT NULL,
    `passing`     TINYINT UNSIGNED NULL DEFAULT NULL,
    `dribbling`   TINYINT UNSIGNED NULL DEFAULT NULL,
    `defending`   TINYINT UNSIGNED NULL DEFAULT NULL,
    `physicality` TINYINT UNSIGNED NULL DEFAULT NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_match_rating_match_player` (`match_id`, `player_id`),
    CONSTRAINT `fk_match_rating_match` FOREIGN KEY (`match_id`) REFERENCES `match` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_match_rating_player` FOREIGN KEY (`player_id`) REFERENCES `player` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET FOREIGN_KEY_CHECKS = 1;
