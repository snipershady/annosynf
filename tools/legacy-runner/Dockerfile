# syntax=docker/dockerfile:1
#
# legacy-runner: esegue Annocript 2.0.1 e i suoi programmi esterni (dna2pep 1.1, CPC2-beta) SOLO per
# produrre i fixture golden dei test di parità (CLAUDE.md §B.12, TODO.md fase 0). Mai usato a runtime da
# annosynf; si elimina con la fase 11 di TODO.md.
#
# Build (il legacy entra in sola lettura come named build context, nulla viene copiato nel repository):
#   tools/legacy-runner/run.sh build
#
# Python 2.7 non esiste nelle Debian correnti: si parte dall'immagine ufficiale python:2.7.18 (Debian 10
# "buster", repository spostati su archive.debian.org). Hadolint: DL3008 (versioni apt non bloccate) non
# si applica perché l'archivio di buster è congelato; DL3022 è un falso positivo sul named context "legacy".

FROM python:2.7.18-slim-buster AS base
RUN printf 'deb http://archive.debian.org/debian buster main\ndeb http://archive.debian.org/debian-security buster/updates main\n' > /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive

# Stage di compilazione: libsvm 3.18 dal tarball di CPC2 (come indica il suo 00README) e le wheel Python.
# Versioni bloccate: Biopython 1.76 è l'ultima per Python 2 e il pI di CPC2 dipende dalla versione.
FROM base AS build
RUN apt-get update && apt-get install -y --no-install-recommends gcc g++ make && rm -rf /var/lib/apt/lists/*
RUN pip wheel --no-cache-dir --wheel-dir /wheels numpy==1.16.6 \
    && pip install --no-cache-dir /wheels/numpy-*.whl \
    && pip wheel --no-cache-dir --no-deps --wheel-dir /wheels biopython==1.76
COPY --from=legacy DL_PROGRAMS/CPC2-beta.tar.gz /tmp/
RUN tar xzf /tmp/CPC2-beta.tar.gz -C /opt --no-same-owner \
    && tar xzf /opt/CPC2-beta/libs/libsvm/libsvm-3.18.tar.gz -C /opt/CPC2-beta/libs/libsvm --no-same-owner
WORKDIR /opt/CPC2-beta/libs/libsvm/libsvm-3.18
RUN make clean && make

FROM base
# Perl + BioPerl e moduli usati da annocript.pl e LIB/*.pm, R per i grafici, client MySQL per la fase 1.
RUN apt-get update && apt-get install -y --no-install-recommends \
        perl bioperl libdbi-perl libdbd-mysql-perl libwww-perl libparallel-forkmanager-perl \
        libterm-readkey-perl liblist-moreutils-perl liberror-perl libcgi-pm-perl libarchive-extract-perl \
        r-base-core default-mysql-client gawk curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN --mount=type=bind,from=build,source=/wheels,target=/wheels pip install --no-cache-dir --no-index /wheels/*.whl

COPY --from=build /opt/CPC2-beta /opt/CPC2-beta
COPY --from=legacy DL_PROGRAMS/dna2pep-1.1.tar.gz /tmp/
# I tarball hanno permessi del loro autore (es. 0750): il container gira con l'uid dell'utente host. I .pyc
# compilati dall'autore si tolgono, così Python esegue i sorgenti. dna2pep.py ha lo shebang /usr/bin/python.
RUN tar xzf /tmp/dna2pep-1.1.tar.gz -C /opt --no-same-owner && rm /tmp/dna2pep-1.1.tar.gz \
    && find /opt/dna2pep-1.1 /opt/CPC2-beta -name '*.pyc' -delete \
    && chmod -R a+rX,go-w /opt/dna2pep-1.1 /opt/CPC2-beta \
    && chmod a+x /opt/dna2pep-1.1/dna2pep.py /opt/CPC2-beta/bin/CPC2.py \
    && ln -s /usr/local/bin/python /usr/bin/python
COPY --from=legacy --chmod=0755 USEFUL/faSomeRecords /usr/local/bin/faSomeRecords
# Il binario faSomeRecords del legacy è linkato a libpng12, che buster non ha più: la si prende dal pacchetto
# di Debian 8 (jessie) sull'archivio ufficiale, verificato con sha256 ed estratto senza dipendenze.
RUN curl -fsSL -o /tmp/libpng12.deb http://archive.debian.org/debian/pool/main/libp/libpng/libpng12-0_1.2.50-2+deb8u3_amd64.deb \
    && echo "fa86f58f9595392dc078abe3b446327089c47b5ed8632c19128a156a1ea68b96  /tmp/libpng12.deb" | sha256sum -c - \
    && dpkg-deb -x /tmp/libpng12.deb / && rm /tmp/libpng12.deb && ldconfig

# Il legacy si monta in sola lettura su /legacy; gli output finiscono in /out.
ENV CPC_HOME=/opt/CPC2-beta \
    DNA2PEP_HOME=/opt/dna2pep-1.1
WORKDIR /out
COPY --chmod=0755 DbCreatorHarness.pm /usr/local/lib/legacy-runner/DbCreatorHarness.pm
COPY --chmod=0755 entrypoint.sh /usr/local/bin/legacy-runner
ENTRYPOINT ["legacy-runner"]
CMD ["versions"]
