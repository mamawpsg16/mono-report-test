# --- Vue SPA dev server ---
FROM node:20-alpine

WORKDIR /app

# Copy dependency files first (cache layer)
COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the app
COPY . .

EXPOSE 5173

# --host 0.0.0.0 lets the container be reached from outside (Docker network)
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
