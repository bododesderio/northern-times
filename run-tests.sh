#!/bin/bash
# run-tests.sh — Run PHPUnit test suite inside the Docker container.
#
# Usage:
#   ./run-tests.sh              # Run all tests
#   ./run-tests.sh unit         # Run only Unit suite
#   ./run-tests.sh integration  # Run only Integration suite
#   ./run-tests.sh feature      # Run only Feature suite
#   ./run-tests.sh --filter ArticleTest  # Filter specific test class
#
# CI Usage (GitHub Actions / GitLab CI):
#   docker compose exec -T app sh -c "cd /var/www/html && vendor/bin/phpunit"

set -e

SUITE="${1:-}"
EXTRA_ARGS="${@:2}"

echo "=== Northern Times — Test Runner ==="
echo ""

# Ensure containers are running
if ! docker compose ps --status running | grep -q northern_times_app; then
    echo "Starting containers..."
    docker compose up -d
    sleep 3
fi

# Build the PHPUnit command
CMD="cd /var/www/html && vendor/bin/phpunit --colors=always"

case "$SUITE" in
    unit)
        CMD="$CMD --testsuite Unit"
        echo "Running: Unit tests"
        ;;
    integration)
        CMD="$CMD --testsuite Integration"
        echo "Running: Integration tests"
        ;;
    feature)
        CMD="$CMD --testsuite Feature"
        echo "Running: Feature tests"
        ;;
    --filter*)
        CMD="$CMD $SUITE $EXTRA_ARGS"
        echo "Running: filtered tests"
        ;;
    "")
        echo "Running: All tests"
        ;;
    *)
        CMD="$CMD $SUITE $EXTRA_ARGS"
        echo "Running: custom args"
        ;;
esac

echo "CMD: $CMD"
echo ""

docker compose exec -T app sh -c "$CMD"

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "All tests passed."
else
    echo "Some tests failed (exit code $EXIT_CODE)."
fi

exit $EXIT_CODE