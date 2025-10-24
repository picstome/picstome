# Dockerized build

Dockerized image is not available due to Flux pro limitations. You need to
build the image yourself.

## Building the image yourself

1. Edit the config files in `docker/config` if necessary
2. Copy the .env.example -> .env and set your preferences
3. Make sure you have `auth.json` for composer authentication at project root
4. Navigate to the `docker` folder and build the image:
```sh
docker compose build
docker compose up -d
```
5. User with default user `admin@example.com:admin` is created
