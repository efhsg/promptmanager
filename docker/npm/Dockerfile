FROM node:20-slim

RUN apt-get update && \
    apt-get install -y git && \
    apt-get install -y git bash && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
RUN chown -R ${USER_ID}:${GROUP_ID} /app

USER ${USER_ID}

COPY --chown=${USER_ID}:${GROUP_ID} package*.json ./

RUN npm install