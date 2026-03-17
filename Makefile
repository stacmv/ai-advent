.PHONY: help install lint test demo record upload clean setup get-token next-day up

help:
	@echo ""
	@echo "*** AI ADVENT - DAY 11: ASSISTANT MEMORY MODEL ***"
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
	@echo "    make up               Start web server (localhost:8080)"
	@echo "    make demo             Run Day 11 demo via API"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make record           Start screen recording and run demo"
	@echo "    make upload           Upload latest video for this day"
	@echo ""
	@echo "  Bootstrap:"
	@echo "    make next-day N=12    Bootstrap next day branch"
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
	@echo "Starting web server on localhost:8080..."
	php -S localhost:8080 -t . days/day11/web.php

demo:
	@echo "Running Day 11 demo..."
	php -r "$$_SERVER['REQUEST_METHOD']='POST'; $$_SERVER['REQUEST_URI']='/days/day11/api/demo/run'; $$_SERVER['CONTENT_TYPE']='application/json'; \$$_GET['case']=1; include 'days/day11/web.php';"

record:
	@echo "Starting screen recording for Day 11 demo..."
	php tools/record.php --day=11

upload:
	@echo "Uploading latest Day 11 video..."
	php tools/upload_latest.php 11

next-day:
	@if [ -z "$(N)" ]; then echo "Usage: make next-day N=<day_number> [T=\"Title\"]"; exit 1; fi
	php tools/bootstrap_day.php $(N) "$(T)"

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
