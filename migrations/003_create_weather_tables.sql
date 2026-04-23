CREATE TABLE IF NOT EXISTS weather_cache
(
    latitude    REAL      NOT NULL,
    longitude   REAL      NOT NULL,
    forecast_ts TIMESTAMP NOT NULL,
    data_json   TEXT      NOT NULL,
    fetched_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (latitude, longitude, forecast_ts)
);
