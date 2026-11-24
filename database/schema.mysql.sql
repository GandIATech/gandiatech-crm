CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_director TINYINT(1) NOT NULL DEFAULT 0,
    avatar_color VARCHAR(7) NOT NULL DEFAULT '#FF5A36',
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    password_changed_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    last_login_at VARCHAR(32) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at VARCHAR(32) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts_email (email, ip, attempted_at),
    KEY idx_login_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_statuses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '#5E6472',
    position INT NOT NULL DEFAULT 0,
    is_won TINYINT(1) NOT NULL DEFAULT 0,
    is_lost TINYINT(1) NOT NULL DEFAULT 0,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_lead_statuses_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(60) NULL,
    company VARCHAR(190) NULL,
    position VARCHAR(120) NULL,
    notes TEXT NULL,
    owner_id INT UNSIGNED NULL,
    deleted_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    updated_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_contacts_owner (owner_id),
    KEY idx_contacts_deleted (deleted_at),
    CONSTRAINT fk_contacts_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    contact_id INT UNSIGNED NULL,
    company VARCHAR(190) NULL,
    value DOUBLE NOT NULL DEFAULT 0,
    status_id INT UNSIGNED NULL,
    source VARCHAR(80) NULL,
    owner_id INT UNSIGNED NULL,
    expected_close_date VARCHAR(20) NULL,
    notes TEXT NULL,
    lost_reason TEXT NULL,
    deleted_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    updated_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_leads_status (status_id),
    KEY idx_leads_owner (owner_id),
    KEY idx_leads_created (created_at),
    KEY idx_leads_deleted (deleted_at),
    CONSTRAINT fk_leads_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_leads_status FOREIGN KEY (status_id) REFERENCES lead_statuses(id) ON DELETE SET NULL,
    CONSTRAINT fk_leads_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    contact_id INT UNSIGNED NULL,
    lead_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'planificado',
    budget DOUBLE NOT NULL DEFAULT 0,
    budgeted_hours DOUBLE NOT NULL DEFAULT 0,
    hourly_rate DOUBLE NOT NULL DEFAULT 0,
    start_date VARCHAR(20) NULL,
    end_date VARCHAR(20) NULL,
    progress INT NOT NULL DEFAULT 0,
    owner_id INT UNSIGNED NULL,
    description TEXT NULL,
    deleted_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    updated_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_projects_status (status),
    KEY idx_projects_deleted (deleted_at),
    CONSTRAINT fk_projects_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    due_date VARCHAR(20) NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'media',
    status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    assigned_to INT UNSIGNED NULL,
    lead_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    completed_at VARCHAR(32) NULL,
    deleted_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    updated_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_tasks_assigned (assigned_to),
    KEY idx_tasks_due (due_date),
    KEY idx_tasks_status (status),
    KEY idx_tasks_deleted (deleted_at),
    CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    title VARCHAR(190) NOT NULL,
    message TEXT NULL,
    link VARCHAR(255) NULL,
    read_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    description TEXT NOT NULL,
    interaction_type VARCHAR(40) NULL,
    activity_date VARCHAR(20) NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_activities_created (created_at),
    CONSTRAINT fk_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS time_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    hours DOUBLE NOT NULL DEFAULT 0,
    description TEXT NULL,
    date VARCHAR(20) NOT NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_time_entries_task (task_id),
    KEY idx_time_entries_project (project_id),
    KEY idx_time_entries_date (date),
    CONSTRAINT fk_time_entries_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_time_entries_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_time_entries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_task_templates_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_template_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'media',
    offset_days INT NOT NULL DEFAULT 0,
    position INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_task_template_items_template (template_id),
    CONSTRAINT fk_task_template_items_template FOREIGN KEY (template_id) REFERENCES task_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(20) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(190) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_attachments_entity (entity_type, entity_id),
    CONSTRAINT fk_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    updated_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    error TEXT NULL,
    created_at VARCHAR(32) NOT NULL DEFAULT '',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
