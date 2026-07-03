# --- Vue SPA dev server ---
FROM node:20-alpine

WORKDIR /app

# Copy dependency files first (cache layer) — patches/ must be present
# before `npm ci` so patch-package's postinstall hook can find its patch files
COPY package.json package-lock.json ./
COPY patches ./patches
RUN npm ci

# Copy the rest of the app
COPY . .

EXPOSE 5173

# --host 0.0.0.0 lets the container be reached from outside (Docker network)
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
