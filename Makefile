.PHONY: help install lint test demo record upload clean setup

help:
	@echo ""
	@echo "🌟 AI ADVENT - DAY 3: REASONING APPROACHES"
	@echo "=========================================="
	@echo ""
	@echo "📋 What this day demonstrates:"
	@echo "   Solve complex problems using different reasoning strategies"
	@echo "   - Direct problem solving (baseline)"
	@echo "   - Step-by-step reasoning instructions"
	@echo "   - Meta-prompting (generating prompts for prompts)"
	@echo "   - Expert group discussion pattern"
	@echo ""
	@echo "🚀 Available commands:"
	@echo ""
	@echo "  Setup:"
	@echo "    make install          Install composer dependencies"
	@echo "    make setup            Copy .env.example to .env"
	@echo ""
	@echo "  Code Quality:"
	@echo "    make lint             Check code style (PSR-12)"
	@echo ""
	@echo "  Running:"
	@echo "    make demo             Run Day 3 demo (river crossing puzzle)"
	@echo "    make test             Run Day 3 interactively"
	@echo ""
	@echo "  Recording & Upload:"
	@echo "    make record           Start screen recording and run demo"
	@echo ""
	@echo "  Utilities:"
	@echo "    make clean            Remove recordings directory"
	@echo ""

install:
	composer install

setup:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		echo "✓ Created .env from .env.example"; \
		echo "⚠️  Please fill in your API keys in .env"; \
	else \
		echo "✓ .env already exists"; \
	fi

lint:
	composer run lint

test:
	@echo "Running Day 3 CLI (interactive mode)..."
	php days/day3/cli.php

demo:
	@echo "Running Day 3 demo (with river crossing puzzle)..."
	php days/day3/cli.php --case=1

record:
	@echo "Starting screen recording for Day 3 demo..."
	php tools/record.php --day=3

clean:
	rm -rf recordings/
	@echo "✓ Cleaned recordings directory"
