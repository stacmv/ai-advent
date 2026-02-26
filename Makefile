.PHONY: help install lint test demo record upload clean setup get-token next-day

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
	@echo "  Running:"
	@echo "    make demo             Run Day 8 demo"
	@echo "    make test             Run Day 8 interactively"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make record           Start screen recording and run demo"
	@echo "    make upload           Upload latest video for this day"
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
	@echo "Running Day 8 CLI (interactive mode)..."
	php days/day8/cli.php

demo:
	@echo "Running Day 8 demo..."
	php days/day8/cli.php --case=1

record:
	@echo "Starting screen recording for Day 8 demo..."
	php tools/record.php --day=8

upload:
	@echo "Uploading latest Day 8 video..."
	php tools/upload_latest.php 8

next-day:
	@if [ -z "$(N)" ]; then echo "Usage: make next-day N=<day_number> [T=\"Title\"]"; exit 1; fi
	php tools/bootstrap_day.php $(N) "$(T)"

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
