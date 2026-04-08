WORKER_CMD = $(CURDIR)/bin/run_worker 'BeachVolleybot\Workers\FileQueueWorker'
LOG_DIR = ../logs

.PHONY: queue-worker-run queue-worker-start queue-worker-stop queue-worker-restart

queue-worker-run:
	$(WORKER_CMD)

queue-worker-start:
	$(WORKER_CMD) 1>/dev/null 2>>$(LOG_DIR)/queue-worker-errors.log &

queue-worker-stop:
	pkill -f '$(CURDIR)/bin/run_worker.*FileQueue[W]orker' || true

queue-worker-restart:
	$(MAKE) queue-worker-stop
	$(MAKE) queue-worker-start