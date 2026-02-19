.PHONY: help install lint test demo record upload clean setup get-token

help:
	@echo ""
	@echo "*** AI ADVENT - DAY 2: RESPONSE FORMAT CONTROL ***"
	@echo "=========================================="
	@echo ""
	@echo "[*] What this day demonstrates:"
	@echo "    Control and constrain LLM output format and size"
	@echo "    - Unconstrained vs constrained responses"
	@echo "    - System prompts for format enforcement (JSON)"
	@echo "    - Token limits and stop sequences"
	@echo "    - How different providers handle constraints"
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
	@echo "    make demo             Run Day 2 demo with test case"
	@echo "    make test             Run Day 2 interactively"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make record           Start screen recording and run demo
	@echo "    make upload           Upload latest video for this day""
	@echo ""
	@echo "  Utilities:"
	@echo "    make clean            Remove recordings directory"
	@echo ""

install:
	composer install

setup:
get-token:
	php tools/get_yandex_token.php
	@if [ ! -f .env ]; then \
get-token:
	php tools/get_yandex_token.php
		cp .env.example .env; \
get-token:
	php tools/get_yandex_token.php
		echo "[+] Created .env from .env.example"; \
get-token:
	php tools/get_yandex_token.php
		echo "[!] Please fill in your API keys in .env"; \
get-token:
	php tools/get_yandex_token.php
	else \
get-token:
	php tools/get_yandex_token.php
		echo "[+] .env already exists"; \
get-token:
	php tools/get_yandex_token.php
	fi
get-token:
	php tools/get_yandex_token.php

get-token:
	php tools/get_yandex_token.php
lint:
	composer run lint

test:
	@echo "Running Day 2 CLI (interactive mode)..."
	php days/day2/cli.php

demo:
	@echo "Running Day 2 demo (with test case)..."
	php days/day2/cli.php --case=1

record:
	@echo "Starting screen recording for Day 2 demo..."
	php tools/record.php --day=2

upload:
	@echo "Uploading latest Day 2 video..."
	php tools/upload_latest.php 2

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
