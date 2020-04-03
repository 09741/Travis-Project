#!/bin/sh
#
# install docker client
#
# this file is kept for historical reasons now, only. instead of
# installing the docker client inside a container, it can be automatically
# injected with the `service: - docker` in yaml, a specific docker client
# can be controlled with `--docker-client` argument.
#
set -u
IFS="$(printf '\n\t ')"

cd build/store/http-cache || exit 2

package="docker-17.12.0-ce.tgz"

if [ ! -f "${package}" ]; then
  curl -fsSLO "https://download.docker.com/linux/static/stable/x86_64/${package}"
  chmod a+rw "${package}"
fi

tar --strip-components=1 -xvzf "${package}" -C /usr/local/bin
