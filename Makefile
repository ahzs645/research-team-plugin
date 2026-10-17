.PHONY: build test lint
lint:
	@for file in research-team-manager.php uninstall.php includes/*.php templates/*.php blocks/team-member-field/render.php; do php -l "$$file" >/dev/null || exit 1; done
	@echo "PHP lint OK"
test: lint
	php tests/updater.php
	php tests/network.php
	php tests/team-access.php
	python3 scripts/check-release.py
build:
	python3 scripts/build.py
