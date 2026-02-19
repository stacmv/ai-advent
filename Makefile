.PHONY: help install lint lint-fix setup get-token next-day clean

help:
	@echo "AI Advent Challenge - Main Branch"
	@echo "=================================="
	@echo ""
	@echo "Setup (Run these first):"
	@echo "  make install          Install composer dependencies"
	@echo "  make setup            Copy .env.example to .env"
	@echo "  make get-token        Get Yandex.Disk OAuth token"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint             Check code style (PSR-12)"
	@echo "  make lint-fix         Auto-fix code style issues"
	@echo ""
	@echo "Bootstrap:"
	@echo "  make next-day N=5     Bootstrap next day branch"
	@echo ""
	@echo "Utilities:"
	@echo "  make clean            Remove recordings directory"
	@echo ""
	@echo "Then switch to a day branch to record/upload:"
	@echo "  git checkout day1     # Day 1: Basic API Call"
	@echo "  git checkout day2     # Day 2: Response Format Control"
	@echo "  git checkout day3     # Day 3: Reasoning Approaches"
	@echo "  git checkout day4     # Day 4: Temperature Comparison"
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

lint-fix:
	composer run lint-fix

next-day:
	@if [ -z "$(N)" ]; then echo "Usage: make next-day N=<day_number> [T=\"Title\"]"; exit 1; fi
	php tools/bootstrap_day.php $(N) "$(T)"

clean:
	rm -rf recordings/
	@echo "[+] Cleaned recordings directory"
