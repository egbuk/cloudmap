APP_SECRET := $(shell printenv APP_SECRET || echo 'secret')
SOURCE_HEIGHT := $(shell printenv SOURCE_HEIGHT || echo '512')
SOURCE_WIDTH := $(shell printenv SOURCE_WIDTH || echo '1024')

build:
	docker build -t heymoon/cloudmap .

build.deploy:
	docker build --platform linux/amd64 -t heymoon/cloudmap . && \
	docker push heymoon/cloudmap

run: build
	docker run -p 8123:80 -e MAP_TILER_TOKEN=$(MAP_TILER_TOKEN) \
		-e SOURCE_HEIGHT=$(SOURCE_HEIGHT) -e SOURCE_WIDTH=$(SOURCE_WIDTH) \
		-e APP_SECRET=$(APP_SECRET) -v valkey:/var/valkey --name cloudmap -d docker.io/heymoon/cloudmap

build.podman:
	podman build -t docker.io/heymoon/cloudmap .

run.podman: build.podman
	podman run -p 8123:80 -e MAP_TILER_TOKEN=$(MAP_TILER_TOKEN) \
		-e SOURCE_HEIGHT=$(SOURCE_HEIGHT) -e SOURCE_WIDTH=$(SOURCE_WIDTH) \
		-e APP_SECRET=$(APP_SECRET) --volume valkey:/var/valkey:Z --name cloudmap --detach docker.io/heymoon/cloudmap

clear:
	docker stop cloudmap || true
	docker rm cloudmap || true

clear.podman:
	podman stop cloudmap || true
	podman rm cloudmap || true

exec:
	docker exec -it cloudmap sh

exec.podman:
	podman exec -it cloudmap sh

update:
	docker exec -t cloudmap flock -n /run/map_update.lock symfony app:update
