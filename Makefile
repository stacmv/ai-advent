.PHONY: help install lint test test-debug demo record upload clean setup get-token next-day serve up down status

help:
	@echo ""
	@echo "*** AI ADVENT - DAY 6: AGENT ARCHITECTURE - BASIC AGENT ***"
	@echo "=========================================="
	@echo ""
	@echo "[*] Available commands:"
	@echo ""
	@echo "  Setup:"
	@echo "    make install          Install composer dependencies"
	@echo "    make setup            Copy .env.example to .env"
	@echo "    make get-token        Get Yandex.Disk OAuth token"
	@echo ""
	@echo "  Code Quality:"
	@echo "    make lint             Check code style (PSR-12)"
	@echo ""
	@echo "  Running (Web UI – primary):"
	@echo "    make up               Start web UI (auto-finds free port, opens browser)"
	@echo "    make down             Stop web UI"
	@echo "    make status           Show server status"
	@echo "    make serve            Alias for 'make up'"
	@echo ""
	@echo "  Legacy CLI (deprecated):"
	@echo "    make demo             Run Day 6 demo (CLI)"
	@echo "    make test             Run Day 6 interactively (CLI)"
	@echo "    make record           Start screen recording and run demo (CLI)"
	@echo "    make upload           Upload latest video (CLI)"
	@echo ""
	@echo "  Bootstrap:"
	@echo "    make next-day N=6     Bootstrap next day branch"
	@echo ""
	@echo "  Utilities:"
	@echo "    make clean            Remove recordings directory"
	@echo ""

install:
	composer install

setup:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "[+] Created .env from .env.example"; \
		echo "[!] Please fill in your API keys in .env"; \
	else \
		echo "[+] .env already exists"; \
	fi

get-token:
	php tools/get_yandex_token.php

lint:
	composer run lint

test:
	@echo "Running Day 6 CLI (interactive mode)..."
	php days/day6/cli.php

serve: up

up:
	@if [ -f .server.pid ] && kill -0 $$(cat .server.pid) 2>/dev/null; then \
		echo "✓ Server already running on http://localhost:$$(cat .server.port) (PID: $$(cat .server.pid))"; \
	else \
		echo "Starting Day 6 web server..."; \
		php tools/serve.php > .server.log 2>&1 & echo $$! > .server.pid; \
		sleep 2; \
		if [ -f .server.pid ] && kill -0 $$(cat .server.pid) 2>/dev/null; then \
			echo "✓ Server started on http://localhost:$$(cat .server.port) (PID: $$(cat .server.pid))"; \
			echo "  Logs: .server.log"; \
		else \
			echo "✗ Failed to start server. Check .server.log"; \
			rm -f .server.pid .server.port; \
			exit 1; \
		fi \
	fi

down:
	@if [ -f .server.pid ]; then \
		PID=$$(cat .server.pid); \
		echo "Stopping server (PID: $$PID)..."; \
		kill $$PID 2>/dev/null && sleep 1 || echo "Process already stopped"; \
		rm -f .server.pid .server.port .server.log; \
		echo "✓ Done"; \
	else \
		echo "ℹ No server running"; \
	fi

status:
	@if [ -f .server.pid ] && kill -0 $$(cat .server.pid) 2>/dev/null; then \
		echo "✓ Server is running on http://localhost:$$(cat .server.port) (PID: $$(cat .server.pid))"; \
	else \
		echo "✗ Server is not running"; \
		rm -f .server.pid .server.port; \
	fi

test-debug:
	@echo "Running Day 6 CLI (debug mode)..."
	php days/day6/cli.php --debug

demo:
	@echo "Running Day 6 demo..."
	php days/day6/cli.php --case=1

record:
	@echo "Starting screen recording for Day 6 demo..."
	php tools/record.php --day=6

upload:
	@echo "Uploading latest Day 6 video..."
	php tools/upload_latest.php 6

next-day:
	@if [ -z "$(N)" ]; then echo "Usage: make next-day N=<day_number> [T=\"Title\"]"; exit 1; fi
	php tools/bootstrap_day.php $(N) "$(T)"

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
