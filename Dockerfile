FROM debian:bookworm-slim

# Install nginx, php-fpm, git, openssh-client
RUN apt-get update && apt-get install -y \
	nginx \
	php8.2-fpm \
	php8.2-cli \
	git \
	openssh-client \
	rsync \
	curl \
	&& rm -rf /var/lib/apt/lists/*

# Nginx config
COPY nginx/site.conf /etc/nginx/sites-available/default

# PHP app files
COPY php/ /app/

# Startup script
COPY scripts/start.sh /start.sh
RUN chmod +x /start.sh

# Web root for repo content
RUN mkdir -p /var/www/html

# SSH known_hosts for github
RUN mkdir -p /root/.ssh && \
	ssh-keyscan github.com >> /root/.ssh/known_hosts && \
	chmod 600 /root/.ssh/known_hosts

# Trust the repo directory for git
RUN git config --global --add safe.directory /repo

# Sessions directory
RUN mkdir -p /var/lib/php/sessions && chmod 777 /var/lib/php/sessions

# Data directory for users file (will be volume-mounted)
RUN mkdir -p /data

EXPOSE 80

CMD ["/start.sh"]
