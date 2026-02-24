#!/usr/bin/bash
# # Download and install fnm:

echo "fnm: $(which fnm), node: $(which node), npm: $(which npm)" ;

if [[ -z $(which fnm) ]] && [[ -z $(fnm current|grep 24) ]] ; then
    curl -sSfL https://fnm.vercel.app/install | bash -s -- --skip-shell --install-dir "/usr/local/bin/" ;
    echo "eval \"\$(fnm env --shell bash)\"" >> ~/.bashrc;
    . ~/.bashrc ;
    fnm install 24 && fnm use 24; fnm default 24 ;
    npm i && npm i -g concurrently nodemon ;
fi
