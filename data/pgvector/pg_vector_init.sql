CREATE
EXTENSION vector;

CREATE TABLE items
(
    id        bigserial PRIMARY KEY,
    namespace text,
    text      text,
    embedding vector(1536)
);
