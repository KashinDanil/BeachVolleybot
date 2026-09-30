-include config/paths.env

APP_WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\AppQueueWorker'
WEATHER_WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\WeatherQueueWorker'
WEATHER_SCAN_WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\WeatherScanWorker'
NOTIFICATION_WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\NotificationQueueWorker'

.PHONY: app-worker-run weather-worker-run weather-scan-worker-run notification-worker-run workers-start workers-stop workers-restart

app-worker-run:
	$(APP_WORKER_CMD)

weather-worker-run:
	$(WEATHER_WORKER_CMD)

weather-scan-worker-run:
	$(WEATHER_SCAN_WORKER_CMD)

notification-worker-run:
	$(NOTIFICATION_WORKER_CMD)

workers-start:
	$(APP_WORKER_CMD) 1>/dev/null 2>>$(CURDIR)/config/$(LOGS_DIR)/app-worker-errors.log &
	$(WEATHER_WORKER_CMD) 1>/dev/null 2>>$(CURDIR)/config/$(LOGS_DIR)/weather-worker-errors.log &
	$(WEATHER_SCAN_WORKER_CMD) 1>/dev/null 2>>$(CURDIR)/config/$(LOGS_DIR)/weather-scan-worker-errors.log &
	$(NOTIFICATION_WORKER_CMD) 1>/dev/null 2>>$(CURDIR)/config/$(LOGS_DIR)/notification-worker-errors.log &

workers-stop:
	pkill -f '$(CURDIR)/bin/run_worker.*AppQueue[W]orker' || true
	pkill -f '$(CURDIR)/bin/run_worker.*WeatherQueue[W]orker' || true
	pkill -f '$(CURDIR)/bin/run_worker.*WeatherScan[W]orker' || true
	pkill -f '$(CURDIR)/bin/run_worker.*NotificationQueue[W]orker' || true

workers-restart:
	$(MAKE) workers-stop
	$(MAKE) workers-start
