CREATE TABLE urls (
    id            bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name          VARCHAR(255),
    created_at    DATE
);