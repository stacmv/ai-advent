.PHONY: help install lint lint-fix test test-day1 test-day2 test-day3 test-day4 record record-day1 record-day2 record-day3 record-day4 clean setup

help:
	@echo "AI Advent Challenge - Available Commands"
	@echo ""
	@echo "Setup:"
	@echo "  make install          Install composer dependencies"
	@echo "  make setup            Copy .env.example to .env"
	@echo ""
	@echo "Code Quality:"
	@echo "  make lint             Check code style (PSR-12)"
	@echo "  make lint-fix         Auto-fix code style issues"
	@echo ""
	@echo "Testing:"
	@echo "  make test-day1        Run Day 1 demo"
	@echo "  make test-day2        Run Day 2 demo"
	@echo "  make test-day3        Run Day 3 demo"
	@echo "  make test-day4        Run Day 4 demo"
	@echo "  make test             Run all day demos"
	@echo ""
	@echo "Recording:"
	@echo "  make record-day1      Record and upload Day 1"
	@echo "  make record-day2      Record and upload Day 2"
	@echo "  make record-day3      Record and upload Day 3"
	@echo "  make record-day4      Record and upload Day 4"
	@echo ""
	@echo "Utilities:"
	@echo "  make clean            Remove recordings directory"

install:
	composer install

setup:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "Created .env from .env.example - please fill in API keys"; \
	else \
		echo ".env already exists"; \
	fi

lint:
	composer run lint

lint-fix:
	composer run lint-fix

test-day1:
	php days/day1/cli.php --case=1

test-day2:
	php days/day2/cli.php --case=1

test-day3:
	php days/day3/cli.php --case=1

test-day4:
	php days/day4/cli.php --case=1

test: test-day1 test-day2 test-day3 test-day4

record-day1:
	php tools/record.php --day=1

record-day2:
	php tools/record.php --day=2

record-day3:
	php tools/record.php --day=3

record-day4:
	php tools/record.php --day=4

clean:
	rm -rf recordings/
	@echo "Cleaned recordings directory"
