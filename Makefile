# Atajos para el entorno Docker de APP FET. Uso: make <tarea>

.PHONY: up down logs migrate seed fresh test lint format artisan ip

up:        ## Levanta base de datos, API y frontend
	docker compose up -d

down:      ## Detiene los contenedores (los datos se conservan)
	docker compose down

logs:      ## Muestra los registros en vivo
	docker compose logs -f

migrate:   ## Aplica migraciones pendientes
	docker compose exec api php artisan migrate

fresh:     ## Reconstruye la base de datos con datos de ejemplo (borra todo)
	docker compose exec api php artisan migrate:fresh --seed

test:      ## Ejecuta las pruebas del backend
	docker compose exec api php artisan test

lint:      ## Revisa el estilo del backend y del frontend
	docker compose exec api ./vendor/bin/pint --test
	cd frontend && npm run lint

format:    ## Corrige el estilo del backend y del frontend
	docker compose exec api ./vendor/bin/pint
	cd frontend && npm run format

artisan:   ## Ejecuta un comando de artisan: make artisan c="route:list"
	docker compose exec api php artisan $(c)

ip:        ## Muestra la dirección para abrir la app desde otro equipo de la misma red
	@IP=$$(ipconfig getifaddr en0 || ipconfig getifaddr en1); \
	echo "App:    http://$$IP:5173"; \
	echo "Correo: http://$$IP:8025"
