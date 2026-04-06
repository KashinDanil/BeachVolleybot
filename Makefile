WORKER_CMD = ./bin/run_worker 'BeachVolleybot\Workers\FileQueueWorker'
LOG_DIR = ../logs

.PHONY: qw queue-worker qwe queue-worker-errors

qw: queue-worker
queue-worker:
	$(WORKER_CMD)

qwe: queue-worker-errors
queue-worker-errors:
	$(WORKER_CMD) 1>/dev/null 2>>$(LOG_DIR)/queue-worker-errors.log