#!/bin/sh
#
# Install crawler crontab inside the Docker container.
# Run once: docker exec northern_times_app sh /var/www/html/cron/install_cron.sh
#
# The cron runs every 5 minutes but only crawls sources whose
# interval has elapsed (Uganda: 15min, others: 30min).
# Lock file prevents overlapping runs.
#

echo "Installing Northern Times crawler cron..."

# Create log directory
mkdir -p /var/www/html/storage/logs

# Write crontab
echo '# Northern Times Crawler — every 5 minutes
*/5 * * * * cd /var/www/html && php cron/crawl.php >> /var/www/html/storage/logs/crawler.log 2>&1
' | crontab -

# Start crond if not running
if ! pgrep -x crond > /dev/null; then
    crond -b -l 8
    echo "✅ crond started"
else
    echo "✅ crond already running"
fi

echo "✅ Crontab installed:"
crontab -l
echo ""
echo "Logs: storage/logs/crawler.log"
echo "Check: docker exec northern_times_app crontab -l"
echo "Test:  docker exec northern_times_app sh -c 'php /var/www/html/cron/crawl.php'"