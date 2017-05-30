php=php
perl=perl
composer=$(php) composer.phar
phpcs=$(php) vendor/bin/phpcs
phpunit=$(php) vendor/bin/phpunit
phpdoc=$(php) vendor/bin/phpdoc
phpdocmd=$(php) vendor/bin/phpdocmd
yaml2json=$(perl) -MJSON -MYAML -eprint -e'to_json(YAML::Load(join""=><>),{canonical=>1,pretty=>1})'
getversion=$(perl) -MYAML -eprint -e'YAML::Load(join""=><>)->{version}'

all: | vendor test docs

info:
	@echo $(php)
	@$(php) -v
	@echo $(perl)
	@$(perl) -v

docs:
	if [ -d $@ ]; then git rm -f $@/*.md; else mkdir $@; fi
	$(phpdoc) -d src/ -t $@ --template=xml --visibility=public >phpdoc.out
	$(phpdocmd) docs/structure.xml docs/ > phpdocmd.out
	git add docs/*.md
	git clean -xdf docs

clean:
	git clean -xdf -e composer.phar -e vendor

vendor:
	$(composer) --prefer-dist install

composer.json: composer.yaml
	$(yaml2json) < $< > $@~
	mv $@~ $@
	-rm composer.lock
	git add $@

test: lint
	$(phpcs) --warning-severity=0 --standard=PSR2 src
	-rm test.sock
	$(phpunit) --verbose tests/

lint:
	for file in `find src tests -name '*.php' | sort`; do $(php) -l $$file || exit 1; done

.PHONY: all info docs clean test
