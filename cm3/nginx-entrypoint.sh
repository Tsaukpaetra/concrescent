#!/bin/sh

DOMAIN=${DOMAIN_NAME:-_}
CERT_DIR="/etc/letsencrypt/live/$DOMAIN"
TEMP_DIR="/etc/nginx/ssl_fallback"
SSL_LINK_DIR="/etc/nginx/ssl"
ENABLE_SSL=${ENABLE_SSL:-false}

#template expected at "/etc/nginx/templates/default.conf.template"
#This becomes the default.conf in the conf directory
#If it's not, the default default.conf won't be impacted by the upcoming mods
TEMPLATE="/etc/nginx/conf.d/default.conf"

#Default command if none was provided
if [ $# -eq 0 ]; then
    set -- nginx -g "daemon off;"
fi
mkdir -p $TEMP_DIR $SSL_LINK_DIR

#Run the container's entrypoint without the ending exec line at the end
sed '/exec "\$@"/d' /docker-entrypoint.sh | sh -s -- "$@"


# 1. Replace Domain Name
sed -i "s/SERVER_DOMAIN_NAME/$DOMAIN/g" "$TEMPLATE"

# 2. Set the proxy upstream for the api endpoint handler

if [ -n "$PROXY_NEXT_HOP" ]; then
    echo "Configuring real_ip tracking for next hop: $PROXY_NEXT_HOP"
    # Swap out the placeholder for the actual environment variable value
    sed -i "s/PROXY_NEXT_HOP/$PROXY_NEXT_HOP/g" "$TEMPLATE"
else
    echo "No PROXY_NEXT_HOP provided. Stripping out proxy real_ip configurations..."
    # Wipe out everything between the proxy markers to keep the configuration clean
    sed -i '/# START_PROXY_CONFIG/,/# END_PROXY_CONFIG/d' "$TEMPLATE"
fi

if [ "$ENABLE_SSL" = "true" ] && [ -n "$DOMAIN_NAME" ] && [ "$DOMAIN_NAME" != "_" ]; then
    echo "Mode: FULL SSL for $DOMAIN_NAME"

    # --- Certificate Fallback Logic ---
    if [ ! -f "$CERT_DIR/fullchain.pem" ]; then
        echo "No real certs (yet). Generating fallback..."
        openssl req -x509 -nodes -days 369 -newkey rsa:2048 \
            -keyout "$TEMP_DIR/privkey.pem" -out "$TEMP_DIR/fullchain.pem" \
            -subj "/CN=$DOMAIN" \
            -addext "subjectAltName=DNS:$DOMAIN"
        ln -sf "$TEMP_DIR/fullchain.pem" "$SSL_LINK_DIR/fullchain.pem"
        ln -sf "$TEMP_DIR/privkey.pem" "$SSL_LINK_DIR/privkey.pem"
        export FALLBACK_MODE=true
    else
        ln -sf "$CERT_DIR/fullchain.pem" "$SSL_LINK_DIR/fullchain.pem"
        ln -sf "$CERT_DIR/privkey.pem" "$SSL_LINK_DIR/privkey.pem"
        export FALLBACK_MODE=false
    fi

    #Start nginx backgrounded so we can continue, using passed-in arguments (if any)
    "$@" &
    NGINX_PID=$!

    if [ "$FALLBACK_MODE" = "true" ]; then
        echo "Waiting for Certbot..."
        for i in $(seq 1 60); do
            if [ -f "$CERT_DIR/fullchain.pem" ]; then
                echo "Certbot success! Reloading..."
                nginx -s reload
                break
            fi
            sleep 10
        done
    fi
    wait $NGINX_PID

else
    echo "Mode: HTTP ONLY"
    
    # 1. Strip the SSL directives and the 443 listener
    # This removes everything between the markers, including the 443 listen line.
    sed -i '/# START_SSL_CONFIG/,/# END_SSL_CONFIG/d' "$TEMPLATE"
    
    # 2. Strip the Redirect logic so port 80 actually serves the app
    # We look for the 'if ($scheme = http)' block and remove it.
    sed -i '/if (\$scheme = http) {/,/}/d' "$TEMPLATE"

    exec "$@"
fi