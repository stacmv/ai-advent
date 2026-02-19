.PHONY: help install lint lint-fix test test-day1 test-day2 test-day3 test-day4 record record-day1 record-day2 record-day3 record-day4 clean setup usecase usecase-day1 usecase-day2 usecase-day3 usecase-day4

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
	@echo "Use Cases (learn what each day demonstrates):"
	@echo "  make usecase          Show all day use cases"
	@echo "  make usecase-day1     Learn about Day 1: Basic API calls"
	@echo "  make usecase-day2     Learn about Day 2: Response constraints"
	@echo "  make usecase-day3     Learn about Day 3: Reasoning approaches"
	@echo "  make usecase-day4     Learn about Day 4: Temperature effects"
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

usecase: usecase-day1 usecase-day2 usecase-day3 usecase-day4

usecase-day1:
	@echo ""
	@echo "=== DAY 1: BASIC API CALL ==="
	@echo ""
	@echo "📋 Use Case:"
	@echo "   Compare basic LLM responses across multiple providers"
	@echo ""
	@echo "🎯 What it demonstrates:"
	@echo "   - How to call Claude, Deepseek, and YandexGPT APIs"
	@echo "   - Response times across different providers"
	@echo "   - Basic prompt handling and response parsing"
	@echo ""
	@echo "🚀 Quick start:"
	@echo "   make test-day1          # Run the demo"
	@echo "   php days/day1/cli.php   # Run without demo case"
	@echo ""
	@echo "💡 Learn about:"
	@echo "   - Unified LLMClient interface (src/LLMClient.php)"
	@echo "   - API endpoints and authentication"
	@echo "   - Response handling and error management"
	@echo ""

usecase-day2:
	@echo ""
	@echo "=== DAY 2: RESPONSE FORMAT CONTROL ==="
	@echo ""
	@echo "📋 Use Case:"
	@echo "   Control and constrain LLM output format and size"
	@echo ""
	@echo "🎯 What it demonstrates:"
	@echo "   - Unconstrained vs constrained responses"
	@echo "   - System prompts for format enforcement"
	@echo "   - Token limits and stop sequences"
	@echo "   - How different providers handle constraints"
	@echo ""
	@echo "🚀 Quick start:"
	@echo "   make test-day2          # Run the demo"
	@echo "   php days/day2/cli.php   # Run without demo case"
	@echo ""
	@echo "💡 Learn about:"
	@echo "   - JSON formatting constraints"
	@echo "   - Max token limits (max_tokens parameter)"
	@echo "   - Stop sequences for structured output"
	@echo "   - Provider-specific behavior differences"
	@echo ""

usecase-day3:
	@echo ""
	@echo "=== DAY 3: REASONING APPROACHES ==="
	@echo ""
	@echo "📋 Use Case:"
	@echo "   Solve complex problems using different reasoning strategies"
	@echo ""
	@echo "🎯 What it demonstrates:"
	@echo "   - Direct problem solving (baseline)"
	@echo "   - Step-by-step reasoning instructions"
	@echo "   - Meta-prompting (generating prompts for prompts)"
	@echo "   - Expert group discussion pattern"
	@echo ""
	@echo "🚀 Quick start:"
	@echo "   make test-day3          # Run the demo (classic river crossing puzzle)"
	@echo "   php days/day3/cli.php   # Run without demo case"
	@echo ""
	@echo "💡 Learn about:"
	@echo "   - How instruction style affects reasoning quality"
	@echo "   - Chain-of-thought prompting"
	@echo "   - Prompt engineering best practices"
	@echo "   - Multi-perspective problem solving"
	@echo ""

usecase-day4:
	@echo ""
	@echo "=== DAY 4: TEMPERATURE COMPARISON ==="
	@echo ""
	@echo "📋 Use Case:"
	@echo "   Understand how temperature affects model behavior"
	@echo ""
	@echo "🎯 What it demonstrates:"
	@echo "   - Temperature 0.0: Deterministic, consistent outputs"
	@echo "   - Temperature 0.7: Balanced creativity and consistency"
	@echo "   - Temperature 1.2: Highly creative and diverse outputs"
	@echo "   - Comparison across all 3 APIs in 3×3 table"
	@echo ""
	@echo "🚀 Quick start:"
	@echo "   make test-day4          # Run the demo (poetry generation)"
	@echo "   php days/day4/cli.php   # Run without demo case"
	@echo ""
	@echo "💡 Learn about:"
	@echo "   - Temperature parameter effects on LLM output"
	@echo "   - When to use low vs high temperature"
	@echo "   - Consistency vs creativity trade-offs"
	@echo "   - How different providers interpret temperature"
	@echo ""
