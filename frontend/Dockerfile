# Build Stage
FROM node:20-alpine as build
WORKDIR /app
COPY package.json .
# Use npm mirror for speed
RUN npm config set registry https://registry.npmmirror.com
# Use npm install strictly
RUN npm install
COPY . .
RUN npm run build

# Production Stage
FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
