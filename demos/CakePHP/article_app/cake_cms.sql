CREATE TABLE users
(
    id       SERIAL PRIMARY KEY,
    email    VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created  TIMESTAMP,
    modified TIMESTAMP
);
--//@UNDO
CREATE TABLE articles
(
    id        SERIAL PRIMARY KEY,
    user_id   INT          NOT NULL,
    title     VARCHAR(255) NOT NULL,
    slug      VARCHAR(191) NOT NULL,
    body      TEXT,
    published BOOLEAN DEFAULT FALSE,
    created   TIMESTAMP,
    modified  TIMESTAMP,
    UNIQUE (slug),
    FOREIGN KEY (user_id) REFERENCES users (id)
);
--//@UNDO
INSERT INTO users (email, password, created, modified)
VALUES ('cakephp@example.com', 'secret', NOW(), NOW());
--//@UNDO
INSERT INTO articles (user_id, title, slug, body, published, created, modified)
VALUES (1, 'First Post', 'first-post', 'This is the first post.', TRUE, NOW(), NOW());
