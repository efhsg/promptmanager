# ./docker/ckeditor/Dockerfile
FROM node:18-alpine

# Install bash
RUN apk add --no-cache bash

# Set the working directory
WORKDIR /app

# Copy package.json and package-lock.json for dependency installation
COPY package*.json ./

# Install dependencies
RUN npm install

# Copy the remaining CKEditor source files (e.g., src/, webpack config, etc.)
# COPY . .

# Build CKEditor
CMD ["npm", "run", "build"]
