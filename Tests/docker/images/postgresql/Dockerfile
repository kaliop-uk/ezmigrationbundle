ARG postgresql_version=9.5

FROM postgres:${postgresql_version}
LABEL postgresql.version=${postgresql_version}

ARG do_shrink_container=true
ARG do_update_os=true
ARG timezone=none

# Configure timezone
# -----------------------------------------------------------------------------
RUN if [ "${timezone}" != "none" ]; then echo "${timezone}" > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata; fi

# Update preinstalled packages
# -----------------------------------------------------------------------------
RUN if [ "${do_update_os}" != "false" ]; then apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -y upgrade ; fi

# Base packages
# -----------------------------------------------------------------------------
#RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -y install \
#    procps

# PG config
# -----------------------------------------------------------------------------
RUN mkdir /etc/postgresql/conf.d
COPY initdb.sh /docker-entrypoint-initdb.d/initdb.sh
RUN chmod 755 /docker-entrypoint-initdb.d/initdb.sh

# Clear archives in apt cache folder to slim down the image
# -----------------------------------------------------------------------------
RUN if [ "${do_shrink_container}" != "false" ]; then apt-get clean && rm -rf /var/lib/apt/lists/*; fi

# Set up entrypoint
# -----------------------------------------------------------------------------
COPY entrypoint.sh /root/entrypoint.sh
RUN chmod 755 /root/entrypoint.sh

EXPOSE 5432

ENTRYPOINT ["/root/entrypoint.sh"]
CMD ["postgres", "-c listen_addresses=*"]
