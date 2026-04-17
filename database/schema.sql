-- ============================================================================
-- KODO TEAM MANAGEMENT - POSTGRESQL DATABASE SCHEMA
-- Converted from MySQL 8.0+ to PostgreSQL 15+
-- Version: 2.0.0
-- ============================================================================

-- Trigger function for auto-updating updated_at timestamps
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- 1. CORE USER MANAGEMENT
-- ============================================================================

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    display_name VARCHAR(150),
    job_title VARCHAR(100),
    department VARCHAR(100),
    phone_number VARCHAR(50),
    avatar_url VARCHAR(500),
    cover_image_url VARCHAR(500),
    bio TEXT,
    timezone VARCHAR(50) DEFAULT 'Europe/Budapest',
    locale VARCHAR(10) DEFAULT 'hu',

    -- Online presence
    presence_status VARCHAR(20) DEFAULT 'offline'
        CHECK (presence_status IN ('online','away','busy','dnd','brb','offline','invisible')),
    presence_message VARCHAR(255),
    presence_expiry TIMESTAMP,
    last_seen_at TIMESTAMP,

    -- Account status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    is_admin BOOLEAN DEFAULT FALSE,

    -- Account lockout
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMP,

    -- Laravel auth
    remember_token VARCHAR(100),
    email_verified_at TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT uk_users_email UNIQUE (email),
    CONSTRAINT uk_users_username UNIQUE (username)
);

CREATE INDEX idx_users_presence ON users(presence_status);

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Laravel Sanctum personal access tokens
CREATE TABLE personal_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_pat_tokenable ON personal_access_tokens(tokenable_type, tokenable_id);

-- ============================================================================
-- 2. ORGANIZATION & WORKSPACE
-- ============================================================================

CREATE TABLE organizations (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    logo_url VARCHAR(500),
    domain VARCHAR(255),
    settings JSONB,
    allowed_email_domains JSONB,
    plan_type VARCHAR(20) DEFAULT 'free'
        CHECK (plan_type IN ('free','standard','business','pro','enterprise')),
    max_members INT DEFAULT 50,
    max_storage_gb INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT uk_organizations_slug UNIQUE (slug)
);

CREATE TRIGGER trg_organizations_updated_at
    BEFORE UPDATE ON organizations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 3. PROJECTS
-- ============================================================================

CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    organization_id INT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100),
    description TEXT,
    color VARCHAR(20) DEFAULT '#7360F9',
    icon VARCHAR(50),
    project_type VARCHAR(20) DEFAULT 'kanban'
        CHECK (project_type IN ('kanban','list','timeline','calendar')),
    status VARCHAR(20) DEFAULT 'active'
        CHECK (status IN ('planning','active','on_hold','completed','archived')),
    start_date DATE,
    target_end_date DATE,
    actual_end_date DATE,
    progress SMALLINT DEFAULT 0 CHECK (progress >= 0 AND progress <= 100),
    settings JSONB,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users(id),
    CONSTRAINT fk_projects_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
);

CREATE INDEX idx_projects_owner ON projects(owner_id);
CREATE INDEX idx_projects_status ON projects(status);

CREATE TRIGGER trg_projects_updated_at
    BEFORE UPDATE ON projects
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 4. TEAMS
-- ============================================================================

CREATE TABLE teams (
    id SERIAL PRIMARY KEY,
    organization_id INT,
    project_id INT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100),
    description TEXT,
    icon_url VARCHAR(500),
    color VARCHAR(20) DEFAULT '#7360F9',
    visibility VARCHAR(20) DEFAULT 'private'
        CHECK (visibility IN ('public','private','hidden')),
    is_private BOOLEAN DEFAULT FALSE,
    password_hash VARCHAR(255),
    is_archived BOOLEAN DEFAULT FALSE,
    archived_at TIMESTAMP,
    archived_by INT,
    owner_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT fk_teams_org FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    CONSTRAINT fk_teams_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_teams_owner FOREIGN KEY (owner_id) REFERENCES users(id),
    CONSTRAINT fk_teams_archived_by FOREIGN KEY (archived_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_teams_project ON teams(project_id);
CREATE INDEX idx_teams_visibility ON teams(visibility);

CREATE TRIGGER trg_teams_updated_at
    BEFORE UPDATE ON teams
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Team members
CREATE TABLE team_members (
    id SERIAL PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(20) DEFAULT 'member'
        CHECK (role IN ('owner','admin','member','guest')),
    notification_level VARCHAR(20) DEFAULT 'all'
        CHECK (notification_level IN ('all','mentions','none')),
    is_favorite BOOLEAN DEFAULT FALSE,
    is_muted BOOLEAN DEFAULT FALSE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    invited_by INT,

    CONSTRAINT uk_team_members UNIQUE (team_id, user_id),
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_team_members_invited FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_team_members_user ON team_members(user_id);

-- ============================================================================
-- 5. CHANNELS
-- ============================================================================

CREATE TABLE channels (
    id SERIAL PRIMARY KEY,
    team_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    channel_type VARCHAR(20) DEFAULT 'standard'
        CHECK (channel_type IN ('general','standard','public','private','announcement')),
    is_default BOOLEAN DEFAULT FALSE,
    allow_threads BOOLEAN DEFAULT TRUE,
    allow_reactions BOOLEAN DEFAULT TRUE,
    allow_mentions BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT uk_channels_slug UNIQUE (team_id, slug),
    CONSTRAINT fk_channels_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_channels_creator FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE INDEX idx_channels_team ON channels(team_id);

CREATE TRIGGER trg_channels_updated_at
    BEFORE UPDATE ON channels
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 6. DIRECT MESSAGES (Simple chat rooms used by backend)
-- ============================================================================

CREATE TABLE chat_rooms (
    id SERIAL PRIMARY KEY,
    room_id BIGINT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP,
    deleted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_chatrooms_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_chatrooms_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_chatrooms_room ON chat_rooms(room_id);
CREATE INDEX idx_chatrooms_sender ON chat_rooms(sender_id);
CREATE INDEX idx_chatrooms_receiver ON chat_rooms(receiver_id);

CREATE TRIGGER trg_chat_rooms_updated_at
    BEFORE UPDATE ON chat_rooms
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Reactions on chat_rooms messages (emoji, one row per user+emoji per message)
CREATE TABLE chat_room_reactions (
    id SERIAL PRIMARY KEY,
    chat_room_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uk_chat_room_reactions UNIQUE (chat_room_id, user_id, emoji),
    CONSTRAINT fk_chat_room_reactions_room FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_room_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_chat_room_reactions_room ON chat_room_reactions(chat_room_id);

-- Attachments on chat_rooms messages (file metadata keyed to a chat_rooms row)
CREATE TABLE chat_room_attachments (
    id SERIAL PRIMARY KEY,
    chat_room_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    file_url VARCHAR(500) NOT NULL,
    width INT,
    height INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_chat_room_attachments_room FOREIGN KEY (chat_room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_room_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_chat_room_attachments_room ON chat_room_attachments(chat_room_id);

-- Conversations (group chats - future use)
CREATE TABLE conversations (
    id SERIAL PRIMARY KEY,
    conversation_type VARCHAR(10) DEFAULT 'direct'
        CHECK (conversation_type IN ('direct','group')),
    name VARCHAR(255),
    icon_url VARCHAR(500),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_conversations_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TRIGGER trg_conversations_updated_at
    BEFORE UPDATE ON conversations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TABLE conversation_participants (
    id SERIAL PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(10) DEFAULT 'member'
        CHECK (role IN ('admin','member')),
    is_muted BOOLEAN DEFAULT FALSE,
    last_read_at TIMESTAMP,
    last_read_message_id INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP,

    CONSTRAINT uk_conv_participants UNIQUE (conversation_id, user_id),
    CONSTRAINT fk_conv_participants_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_conv_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_conv_participants_user ON conversation_participants(user_id);

-- ============================================================================
-- 7. MESSAGING SYSTEM (Channel messages - future use)
-- ============================================================================

CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    channel_id INT,
    conversation_id INT,
    parent_message_id INT,
    thread_reply_count INT DEFAULT 0,
    thread_last_reply_at TIMESTAMP,
    sender_id INT NOT NULL,
    content TEXT,
    content_type VARCHAR(20) DEFAULT 'text'
        CHECK (content_type IN ('text','rich_text','code','system','mixed')),
    formatted_content JSONB,
    has_attachments BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_announcement BOOLEAN DEFAULT FALSE,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT fk_messages_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_parent FOREIGN KEY (parent_message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
);

CREATE INDEX idx_messages_channel ON messages(channel_id, created_at);
CREATE INDEX idx_messages_conversation ON messages(conversation_id, created_at);
CREATE INDEX idx_messages_thread ON messages(parent_message_id);
CREATE INDEX idx_messages_sender ON messages(sender_id);

-- Full-text search index (PostgreSQL GIN)
CREATE INDEX idx_messages_content_fts ON messages USING GIN (to_tsvector('hungarian', COALESCE(content, '')));

-- Message attachments
CREATE TABLE message_attachments (
    id SERIAL PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size BIGINT,
    file_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    width INT,
    height INT,
    duration_seconds INT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE INDEX idx_attachments_message ON message_attachments(message_id);

-- Message reactions
CREATE TABLE message_reactions (
    id SERIAL PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uk_reactions UNIQUE (message_id, user_id, emoji),
    CONSTRAINT fk_reactions_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_reactions_message ON message_reactions(message_id);

-- Message mentions
CREATE TABLE message_mentions (
    id SERIAL PRIMARY KEY,
    message_id INT NOT NULL,
    mention_type VARCHAR(20) NOT NULL
        CHECK (mention_type IN ('user','team','channel','everyone','here')),
    mentioned_id INT,

    CONSTRAINT uk_mentions UNIQUE (message_id, mention_type, mentioned_id),
    CONSTRAINT fk_mentions_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);

CREATE INDEX idx_mentions_message ON message_mentions(message_id);

-- ============================================================================
-- 8. TASK MANAGEMENT
-- ============================================================================

-- Task buckets (Kanban columns)
CREATE TABLE task_buckets (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    key VARCHAR(50),
    color VARCHAR(7),
    position INT DEFAULT 0,
    wip_limit INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_buckets_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE INDEX idx_buckets_project ON task_buckets(project_id);

-- Tasks
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL,
    team_id INT,
    bucket_id INT,
    parent_task_id INT,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'todo'
        CHECK (status IN ('todo','in_progress','in_review','done','blocked','cancelled')),
    priority VARCHAR(10) DEFAULT 'medium'
        CHECK (priority IN ('urgent','high','medium','low','none')),
    start_date DATE,
    due_date DATE,
    completed_at TIMESTAMP,
    estimated_hours DECIMAL(10,2),
    actual_hours DECIMAL(10,2),
    progress SMALLINT DEFAULT 0 CHECK (progress >= 0 AND progress <= 100),
    position INT DEFAULT 0,
    labels JSONB,
    metadata JSONB,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_bucket FOREIGN KEY (bucket_id) REFERENCES task_buckets(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_parent FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE INDEX idx_tasks_project ON tasks(project_id);
CREATE INDEX idx_tasks_team ON tasks(team_id);
CREATE INDEX idx_tasks_bucket ON tasks(bucket_id);
CREATE INDEX idx_tasks_status ON tasks(status);
CREATE INDEX idx_tasks_due_date ON tasks(due_date);
CREATE INDEX idx_tasks_parent ON tasks(parent_task_id);

CREATE TRIGGER trg_tasks_updated_at
    BEFORE UPDATE ON tasks
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Task assignees
CREATE TABLE task_assignees (
    id SERIAL PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,

    CONSTRAINT uk_task_assignees UNIQUE (task_id, user_id),
    CONSTRAINT fk_task_assignees_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_assignees_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_assignees_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_task_assignees_user ON task_assignees(user_id);

-- Task checklists
CREATE TABLE task_checklists (
    id SERIAL PRIMARY KEY,
    task_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_task_checklists_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

CREATE INDEX idx_task_checklists_task ON task_checklists(task_id);

-- Task checklist items
CREATE TABLE task_checklist_items (
    id SERIAL PRIMARY KEY,
    checklist_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP,
    completed_by INT,
    position INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_checklist_items_checklist FOREIGN KEY (checklist_id) REFERENCES task_checklists(id) ON DELETE CASCADE,
    CONSTRAINT fk_checklist_items_completed_by FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_checklist_items_checklist ON task_checklist_items(checklist_id);

-- ============================================================================
-- 9. CALENDAR & EVENTS
-- ============================================================================

CREATE TABLE calendar_events (
    id SERIAL PRIMARY KEY,
    team_id INT,
    channel_id INT,
    organizer_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(500),
    is_online_meeting BOOLEAN DEFAULT FALSE,
    meeting_url VARCHAR(500),
    meeting_id VARCHAR(100),
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    is_all_day BOOLEAN DEFAULT FALSE,
    timezone VARCHAR(50) DEFAULT 'Europe/Budapest',
    is_recurring BOOLEAN DEFAULT FALSE,
    recurrence_rule VARCHAR(500),
    recurrence_end_date DATE,
    parent_event_id INT,
    status VARCHAR(20) DEFAULT 'confirmed'
        CHECK (status IN ('tentative','confirmed','cancelled')),
    reminder_minutes INT DEFAULT 15,
    color VARCHAR(7),
    category VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT fk_events_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_organizer FOREIGN KEY (organizer_id) REFERENCES users(id),
    CONSTRAINT fk_events_parent FOREIGN KEY (parent_event_id) REFERENCES calendar_events(id) ON DELETE CASCADE
);

CREATE INDEX idx_events_team ON calendar_events(team_id);
CREATE INDEX idx_events_organizer ON calendar_events(organizer_id);
CREATE INDEX idx_events_time ON calendar_events(start_time, end_time);

CREATE TRIGGER trg_calendar_events_updated_at
    BEFORE UPDATE ON calendar_events
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Event attendees
CREATE TABLE event_attendees (
    id SERIAL PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    response_status VARCHAR(20) DEFAULT 'pending'
        CHECK (response_status IN ('pending','accepted','declined','tentative')),
    is_required BOOLEAN DEFAULT TRUE,
    responded_at TIMESTAMP,

    CONSTRAINT uk_event_attendees UNIQUE (event_id, user_id),
    CONSTRAINT fk_attendees_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendees_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_attendees_user ON event_attendees(user_id);

-- ============================================================================
-- 10. FILES & DOCUMENTS
-- ============================================================================

CREATE TABLE files (
    id SERIAL PRIMARY KEY,
    team_id INT,
    channel_id INT,
    folder_id INT,
    name VARCHAR(255) NOT NULL,
    file_type VARCHAR(10) DEFAULT 'file'
        CHECK (file_type IN ('file','folder','link')),
    mime_type VARCHAR(100),
    size_bytes BIGINT,
    storage_path VARCHAR(500),
    storage_provider VARCHAR(50) DEFAULT 'local',
    public_url VARCHAR(500),
    version INT DEFAULT 1,
    parent_version_id INT,
    metadata JSONB,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,

    CONSTRAINT fk_files_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES files(id) ON DELETE CASCADE,
    CONSTRAINT fk_files_parent_version FOREIGN KEY (parent_version_id) REFERENCES files(id) ON DELETE SET NULL,
    CONSTRAINT fk_files_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE INDEX idx_files_team ON files(team_id);
CREATE INDEX idx_files_channel ON files(channel_id);

CREATE TRIGGER trg_files_updated_at
    BEFORE UPDATE ON files
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 11. NOTIFICATIONS
-- ============================================================================

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    actor_id INT,
    team_id INT,
    channel_id INT,
    message_id INT,
    task_id INT,
    event_id INT,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    action_url VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    is_push_sent BOOLEAN DEFAULT FALSE,
    is_email_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_channel FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_event FOREIGN KEY (event_id) REFERENCES calendar_events(id) ON DELETE CASCADE
);

CREATE INDEX idx_notifications_user ON notifications(user_id, is_read, created_at);

-- ============================================================================
-- 12. USER SETTINGS & PREFERENCES
-- ============================================================================

CREATE TABLE user_settings (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    theme VARCHAR(10) DEFAULT 'dark'
        CHECK (theme IN ('dark','light','system')),
    language VARCHAR(10) DEFAULT 'hu',
    date_format VARCHAR(20) DEFAULT 'YYYY-MM-DD',
    time_format VARCHAR(5) DEFAULT '24h'
        CHECK (time_format IN ('12h','24h')),
    notifications_enabled BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT TRUE,
    notification_sound BOOLEAN DEFAULT TRUE,
    desktop_notifications BOOLEAN DEFAULT TRUE,
    dnd_enabled BOOLEAN DEFAULT FALSE,
    dnd_start_time TIME,
    dnd_end_time TIME,
    enter_to_send BOOLEAN DEFAULT TRUE,
    show_typing_indicator BOOLEAN DEFAULT TRUE,
    show_read_receipts BOOLEAN DEFAULT TRUE,
    reduce_motion BOOLEAN DEFAULT FALSE,
    high_contrast BOOLEAN DEFAULT FALSE,
    font_size VARCHAR(10) DEFAULT 'medium'
        CHECK (font_size IN ('small','medium','large','xlarge')),
    show_online_status BOOLEAN DEFAULT TRUE,
    allow_direct_messages VARCHAR(20) DEFAULT 'everyone'
        CHECK (allow_direct_messages IN ('everyone','team_members','nobody')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uk_user_settings_user UNIQUE (user_id),
    CONSTRAINT fk_user_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TRIGGER trg_user_settings_updated_at
    BEFORE UPDATE ON user_settings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 13. TIME TRACKING
-- ============================================================================

CREATE TABLE time_entries (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT,
    task_id INT,
    hours DECIMAL(10,2) NOT NULL CHECK (hours > 0 AND hours <= 24),
    date DATE NOT NULL,
    activity_type VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_time_entries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_time_entries_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_time_entries_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
);

CREATE INDEX idx_time_entries_user ON time_entries(user_id, date);
CREATE INDEX idx_time_entries_project ON time_entries(project_id);

CREATE TRIGGER trg_time_entries_updated_at
    BEFORE UPDATE ON time_entries
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 14. FRIENDSHIPS
-- ============================================================================

CREATE TABLE friends (
    id SERIAL PRIMARY KEY,
    user_id_1 INT NOT NULL,
    user_id_2 INT NOT NULL,
    status VARCHAR(20) DEFAULT 'pending'
        CHECK (status IN ('pending','accepted','blocked','declined')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uk_friends UNIQUE (user_id_1, user_id_2),
    CONSTRAINT fk_friends_user1 FOREIGN KEY (user_id_1) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_friends_user2 FOREIGN KEY (user_id_2) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_friends_not_self CHECK (user_id_1 <> user_id_2)
);

CREATE INDEX idx_friends_user2 ON friends(user_id_2);

CREATE TRIGGER trg_friends_updated_at
    BEFORE UPDATE ON friends
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- 15. PARTICIPANTS (Polymorphic project/team membership)
-- ============================================================================

CREATE TABLE participants (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('project','team')),
    entity_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(20) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uk_participants UNIQUE (entity_type, entity_id, user_id),
    CONSTRAINT fk_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_participants_entity ON participants(entity_type, entity_id);
CREATE INDEX idx_participants_user ON participants(user_id);

-- ============================================================================
-- 16. ACTIVITY LOGS
-- ============================================================================

CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_activity_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_activity_logs_user ON activity_logs(user_id, created_at);
CREATE INDEX idx_activity_logs_target ON activity_logs(target_type, target_id);

-- ============================================================================
-- 16b. VERIFICATION CODES (2FA login verification)
-- ============================================================================

CREATE TABLE verification_codes (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    method VARCHAR(10) NOT NULL CHECK (method IN ('email','sms')),
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_verification_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_verification_codes_user ON verification_codes(user_id, created_at);

-- ============================================================================
-- 16c. TRUSTED DEVICES (remember device for 30 days)
-- ============================================================================

CREATE TABLE trusted_devices (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    device_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_trusted_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_trusted_devices_user ON trusted_devices(user_id);
CREATE INDEX idx_trusted_devices_token ON trusted_devices(device_token);

-- ============================================================================
-- 17. USEFUL VIEWS
-- ============================================================================

CREATE OR REPLACE VIEW v_team_members_active AS
SELECT
    tm.team_id,
    tm.user_id,
    tm.role,
    tm.joined_at,
    u.username,
    u.display_name,
    u.email,
    u.avatar_url,
    u.presence_status,
    u.job_title
FROM team_members tm
JOIN users u ON tm.user_id = u.id
WHERE u.deleted_at IS NULL;

CREATE OR REPLACE VIEW v_project_task_summary AS
SELECT
    p.id AS project_id,
    p.name AS project_name,
    COUNT(t.id) AS total_tasks,
    COUNT(CASE WHEN t.status = 'todo' THEN 1 END) AS todo_count,
    COUNT(CASE WHEN t.status = 'in_progress' THEN 1 END) AS in_progress_count,
    COUNT(CASE WHEN t.status = 'in_review' THEN 1 END) AS in_review_count,
    COUNT(CASE WHEN t.status = 'done' THEN 1 END) AS done_count,
    COUNT(CASE WHEN t.status = 'blocked' THEN 1 END) AS blocked_count,
    ROUND(
        COUNT(CASE WHEN t.status = 'done' THEN 1 END)::NUMERIC /
        NULLIF(COUNT(t.id), 0) * 100, 2
    ) AS completion_percentage
FROM projects p
LEFT JOIN tasks t ON t.project_id = p.id AND t.deleted_at IS NULL
WHERE p.deleted_at IS NULL
GROUP BY p.id, p.name;

-- ============================================================================
-- 18. SEED DATA
-- ============================================================================

-- Default organization
INSERT INTO organizations (name, slug, description, plan_type, max_members)
VALUES ('Kodo Labs', 'kodo-labs', 'Kodo Team Management Platform', 'business', 50);

-- ============================================================================
-- 19. LARAVEL SUPPORT TABLES
-- ============================================================================

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity INT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_activity ON sessions(last_activity);

CREATE TABLE IF NOT EXISTS cache (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT NOT NULL,
    expiration INT NOT NULL
);

CREATE TABLE IF NOT EXISTS cache_locks (
    key VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL
);

CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INT,
    available_at INT NOT NULL,
    created_at INT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_jobs_queue ON jobs(queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options TEXT,
    cancelled_at INT,
    created_at INT NOT NULL,
    finished_at INT
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP
);

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
