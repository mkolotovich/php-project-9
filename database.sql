CREATE TABLE urls (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name          VARCHAR(255),
    created_at    DATE
);

CREATE TABLE url_checks (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    url_id        bigint,
    status_code   integer,
    h1            text,
    title         text,
    description   text,
    created_at    DATE
);