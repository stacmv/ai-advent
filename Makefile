.PHONY: help install lint test demo record upload clean setup

help:
	@echo ""
	@echo "*** AI ADVENT - DAY 1: BASIC API CALL ***"
	@echo "=========================================="
	@echo ""
	@echo "[*] What this day demonstrates:"
	@echo "    Compare basic LLM responses across multiple providers"
	@echo "    - How to call Claude, Deepseek, and YandexGPT APIs"
	@echo "    - Response times across different providers"
	@echo "    - Basic prompt handling and response parsing"
	@echo ""
	@echo "[*] Available commands:"
	@echo ""
	@echo "  Setup:"
	@echo "    make install          Install composer dependencies"
	@echo "    make setup            Copy .env.example to .env"
	@echo ""
	@echo "  Code Quality:"
	@echo "    make lint             Check code style (PSR-12)"
	@echo ""
	@echo "  Running:"
	@echo "    make demo             Run Day 1 demo with test case"
	@echo "    make test             Run Day 1 interactively"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make record           Start screen recording and run demo"
	@echo "    make upload           Upload latest video for this day"
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

lint:
	composer run lint

test:
	@echo "Running Day 1 CLI (interactive mode)..."
	php days/day1/cli.php

demo:
	@echo "Running Day 1 demo (with test case)..."
	php days/day1/cli.php --case=1

record:
	@echo "Starting screen recording for Day 1 demo..."
	php tools/record.php --day=1

upload:
	@echo "Uploading latest Day 1 video..."
	php tools/upload_latest.php 1

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
