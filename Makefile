.PHONY: help install lint upload clean setup get-token next-day serve up down status

help:
	@echo ""
	@echo "*** AI ADVENT - DAY 8: TOKEN COUNTING AND LIMIT AWARENESS ***"
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
	@echo "  Running (Web UI):"
	@echo "    make up               Start web UI (auto-finds free port, opens browser)"
	@echo "    make down             Stop web UI"
	@echo "    make status           Show server status"
	@echo "    make serve            Alias for 'make up'"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make upload           Upload latest video for this day"
	@echo ""
	@echo "  Bootstrap:"
	@echo "    make next-day N=9     Bootstrap next day branch"
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

serve: up

up:
	@if [ -f .server.pid ] && kill -0 $$(cat .server.pid) 2>/dev/null; then \
		echo "✓ Server already running on http://localhost:$$(cat .server.port) (PID: $$(cat .server.pid))"; \
	else \
		echo "Starting Day 8 web client at http://localhost:... "; \
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

upload:
	@echo "Uploading latest Day 8 video..."
	php tools/upload_latest.php 8

next-day:
	@if [ -z "$(N)" ]; then echo "Usage: make next-day N=<day_number> [T=\"Title\"]"; exit 1; fi
	php tools/bootstrap_day.php $(N) "$(T)"

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
