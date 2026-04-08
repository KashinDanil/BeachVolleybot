WORKER_CMD = ./bin/run_worker 'BeachVolleybot\Workers\FileQueueWorker'
LOG_DIR = ../logs

.PHONY: qws queue-worker-sync qw queue-worker qwr queue-worker-restart

qws: queue-worker-sync
queue-worker-sync:
	$(WORKER_CMD)

qw: queue-worker
queue-worker:
	$(WORKER_CMD) 1>/dev/null 2>>$(LOG_DIR)/queue-worker-errors.log &

qwr: queue-worker-restart
queue-worker-restart:
	pkill -f 'FileQueueWorker' || true
	$(MAKE) queue-worker