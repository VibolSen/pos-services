# ==============================================================================
# CodeBridges Enterprise Cloud Gateway & Services
# ==============================================================================
FROM nginx:alpine

# Copy API Gateway configuration
COPY ./gateway/nginx.conf /etc/nginx/nginx.conf

# Expose HTTP port for Render Web Service
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
