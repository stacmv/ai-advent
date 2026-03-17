.PHONY: help install lint test demo record upload clean setup get-token next-day up down

help:
	@echo ""
	@echo "*** AI ADVENT - DAY 12: PERSONALIZED ASSISTANT ***"
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
	@echo "  Running:"
	@echo "    make up               Start web server (auto-detects free port)"
	@echo "    make down             Stop web server"
	@echo "    make demo             Run Day 12 demo via API"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make record           Start screen recording and run demo"
	@echo "    make upload           Upload latest video for this day"
	@echo ""
	@echo "  Bootstrap:"
	@echo "    make next-day N=13    Bootstrap next day branch"
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

up:
	@if [ -f .server.pid ] && kill -0 $$(cat .server.pid) 2>/dev/null; then \
		echo "✓ Server already running on http://localhost:$$(cat .server.port) (PID: $$(cat .server.pid))"; \
	else \
		echo "Starting Day 12 web server on free port..."; \
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
		kill $$(cat .server.pid) 2>/dev/null && echo "✓ Server stopped" || true; \
		rm -f .server.pid .server.port .server.log; \
	else \
		echo "No server running"; \
	fi

demo:
	@echo "Running Day 12 demo..."
	php -r "$$_SERVER['REQUEST_METHOD']='POST'; $$_SERVER['REQUEST_URI']='/days/day12/api/demo/run'; $$_SERVER['CONTENT_TYPE']='application/json'; \$$_GET['case']=1; include 'days/day12/web.php';"

record:
	@echo "Starting screen recording for Day 12 demo..."
	php tools/record.php --day=12

upload:
	@echo "Uploading latest Day 12 video..."
	php tools/upload_latest.php 12

next-day:
	@if [ -z "$(N)" ]; then echo "Usage: make next-day N=<day_number> [T=\"Title\"]"; exit 1; fi
	php tools/bootstrap_day.php $(N) "$(T)"

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
