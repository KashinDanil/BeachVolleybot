-include config/paths.env

APP_WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\AppQueueWorker'
WEATHER_WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\WeatherQueueWorker'

.PHONY: app-worker-run weather-worker-run workers-start workers-stop workers-restart

app-worker-run:
	$(APP_WORKER_CMD)

weather-worker-run:
	$(WEATHER_WORKER_CMD)

workers-start:
	$(APP_WORKER_CMD) 1>/dev/null 2>>$(CURDIR)/config/$(LOGS_DIR)/app-worker-errors.log &
	$(WEATHER_WORKER_CMD) 1>/dev/null 2>>$(CURDIR)/config/$(LOGS_DIR)/weather-worker-errors.log &

workers-stop:
	pkill -f '$(CURDIR)/bin/run_worker.*AppQueue[W]orker' || true
	pkill -f '$(CURDIR)/bin/run_worker.*WeatherQueue[W]orker' || true

workers-restart:
	$(MAKE) workers-stop
	$(MAKE) workers-start
