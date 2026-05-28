from __future__ import annotations

import re
import textwrap
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path


PROJETO = "CriaVibe"
RESPONSAVEL_TECNICO = "Willian Batista Oliveira"
REGISTRADOR = "agente-willianbo"

PASTAS_IGNORADAS = {
    ".git",
    ".github",
    ".pytest_cache",
    "__pycache__",
    "node_modules",
    "vendor",
    "uploads",
}

ARQUIVOS_IGNORADOS = {
    ".env",
    ".env.local",
    "CREDENCIAIS.md",
    "api/error.log",
    "documentacao/manual/Manual_Tecnico_CriaVibe.md",
    "documentacao/manual/Manual_Tecnico_CriaVibe.pdf",
}

EXTENSOES_TEXTO = {
    ".css",
    ".dockerignore",
    ".env.example",
    ".html",
    ".js",
    ".json",
    ".md",
    ".php",
    ".py",
    ".sql",
    ".txt",
    ".yaml",
    ".yml",
}

NOMES_TEXTO = {
    ".gitignore",
    "Dockerfile",
    "Procfile",
    "router.php",
}

EXTENSOES_IMAGEM = {".png", ".jpg", ".jpeg", ".webp", ".gif", ".svg"}
EXTENSOES_BINARIAS = {
    ".mp4",
    ".pdf",
    ".zip",
    ".ico",
    ".log",
}

LINGUAGENS = {
    ".css": "css",
    ".html": "html",
    ".js": "javascript",
    ".json": "json",
    ".md": "markdown",
    ".php": "php",
    ".py": "python",
    ".sql": "sql",
    ".txt": "text",
    ".yaml": "yaml",
    ".yml": "yaml",
}


@dataclass(frozen=True)
class ArquivoInfo:
    caminho: Path
    relativo: str
    linhas: int
    tamanho: int


def encontrar_raiz() -> Path:
    caminho = Path(__file__).resolve()
    for candidato in caminho.parents:
        if (candidato / "api").exists() and (candidato / "index.html").exists():
            return candidato
    return caminho.parents[2]


def rel(caminho: Path, raiz: Path) -> str:
    return caminho.relative_to(raiz).as_posix()


def caminho_ignorado(caminho: Path, raiz: Path) -> bool:
    relativo = rel(caminho, raiz)
    partes = set(Path(relativo).parts)
    if partes & PASTAS_IGNORADAS:
        return True
    return relativo in ARQUIVOS_IGNORADOS or caminho.name in ARQUIVOS_IGNORADOS


def e_texto_documentavel(caminho: Path, raiz: Path) -> bool:
    if caminho_ignorado(caminho, raiz):
        return False
    if caminho.name in NOMES_TEXTO:
        return True
    sufixo = caminho.suffix.lower()
    return sufixo in EXTENSOES_TEXTO and sufixo not in EXTENSOES_BINARIAS


def e_imagem_documentavel(caminho: Path, raiz: Path) -> bool:
    if caminho_ignorado(caminho, raiz):
        return False
    return caminho.suffix.lower() in EXTENSOES_IMAGEM


def listar_arquivos_texto(raiz: Path) -> list[ArquivoInfo]:
    arquivos: list[ArquivoInfo] = []
    for caminho in raiz.rglob("*"):
        if caminho.is_file() and e_texto_documentavel(caminho, raiz):
            texto = ler_texto(caminho)
            arquivos.append(
                ArquivoInfo(
                    caminho=caminho,
                    relativo=rel(caminho, raiz),
                    linhas=len(texto.splitlines()),
                    tamanho=caminho.stat().st_size,
                )
            )
    return sorted(arquivos, key=lambda item: item.relativo.lower())


def listar_imagens(raiz: Path) -> list[ArquivoInfo]:
    imagens: list[ArquivoInfo] = []
    for caminho in raiz.rglob("*"):
        if caminho.is_file() and e_imagem_documentavel(caminho, raiz):
            imagens.append(
                ArquivoInfo(
                    caminho=caminho,
                    relativo=rel(caminho, raiz),
                    linhas=0,
                    tamanho=caminho.stat().st_size,
                )
            )
    return sorted(imagens, key=lambda item: item.relativo.lower())


def listar_trabalhos(raiz: Path) -> list[Path]:
    pasta = raiz / "documentacao" / "trabalho"
    trabalhos = [p for p in pasta.glob("trabalho_*.md") if p.is_file()]

    def chave(caminho: Path) -> tuple[int, int, int, str]:
        m = re.search(r"trabalho_(\d{2})_(\d{2})_(\d{4})", caminho.name)
        if not m:
            return (9999, 99, 99, caminho.name)
        dia, mes, ano = map(int, m.groups())
        return (ano, mes, dia, caminho.name)

    return sorted(trabalhos, key=chave)


def ler_texto(caminho: Path) -> str:
    try:
        return caminho.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return caminho.read_text(encoding="utf-8", errors="replace")


def linguagem(caminho: Path) -> str:
    if caminho.name == "Dockerfile":
        return "dockerfile"
    return LINGUAGENS.get(caminho.suffix.lower(), "text")


def tamanho_humano(bytes_: int) -> str:
    unidades = ["B", "KB", "MB", "GB"]
    valor = float(bytes_)
    for unidade in unidades:
        if valor < 1024 or unidade == unidades[-1]:
            return f"{valor:.1f} {unidade}" if unidade != "B" else f"{int(valor)} B"
        valor /= 1024
    return f"{bytes_} B"


def escapar_md(texto: str) -> str:
    return texto.replace("|", "\\|")


def gerar_arvore(raiz: Path) -> str:
    linhas = [raiz.name + "/"]

    def filhos(pasta: Path) -> list[Path]:
        itens = []
        for item in pasta.iterdir():
            if item.name in PASTAS_IGNORADAS:
                itens.append(item)
                continue
            if caminho_ignorado(item, raiz):
                continue
            itens.append(item)
        return sorted(itens, key=lambda p: (not p.is_dir(), p.name.lower()))

    def caminhar(pasta: Path, prefixo: str = "") -> None:
        itens = filhos(pasta)
        for indice, item in enumerate(itens):
            ultimo = indice == len(itens) - 1
            conector = "`-- " if ultimo else "|-- "
            nome = item.name + ("/" if item.is_dir() else "")
            if item.name in PASTAS_IGNORADAS:
                nome += " [ignorado no manual]"
                linhas.append(prefixo + conector + nome)
                continue
            linhas.append(prefixo + conector + nome)
            if item.is_dir():
                caminhar(item, prefixo + ("    " if ultimo else "|   "))

    caminhar(raiz)
    return "\n".join(linhas)


def gerar_indice() -> str:
    secoes = [
        "1. Capa e Identificacao",
        "2. Indice",
        "3. Sumario Executivo",
        "4. Stack e Arquitetura",
        "5. Hierarquia de Pastas e Subpastas",
        "6. Inventario Completo de Arquivos",
        "7. Imagens e Midias do Projeto",
        "8. Registros de Trabalho em Ordem Cronologica",
        "9. Codigo Fonte Completo",
        "10. Criterios de Regeneracao",
    ]
    return "\n".join(f"- [{item}](#{slug(item)})" for item in secoes)


def slug(texto: str) -> str:
    texto = texto.lower()
    texto = re.sub(r"[^a-z0-9\s-]", "", texto)
    return re.sub(r"\s+", "-", texto).strip("-")


def gerar_inventario(arquivos: list[ArquivoInfo]) -> str:
    linhas = ["| Arquivo | Linhas | Tamanho |", "|---|---:|---:|"]
    for arquivo in arquivos:
        linhas.append(
            f"| `{escapar_md(arquivo.relativo)}` | {arquivo.linhas} | {tamanho_humano(arquivo.tamanho)} |"
        )
    return "\n".join(linhas)


def gerar_imagens(imagens: list[ArquivoInfo]) -> str:
    if not imagens:
        return "_Nenhuma imagem documentavel encontrada._"
    linhas = ["| Imagem | Tamanho | Preview |", "|---|---:|---|"]
    for imagem in imagens:
        caminho_preview = "../../" + imagem.relativo
        linhas.append(
            f"| `{escapar_md(imagem.relativo)}` | {tamanho_humano(imagem.tamanho)} | ![]({caminho_preview}) |"
        )
    return "\n".join(linhas)


def gerar_registros_trabalho(raiz: Path, trabalhos: list[Path]) -> str:
    partes: list[str] = []
    for trabalho in trabalhos:
        partes.append(f"### {trabalho.name}\n")
        partes.append(f"Fonte: `{rel(trabalho, raiz)}`\n")
        partes.append(ler_texto(trabalho).strip())
        partes.append("")
    return "\n\n".join(partes)


def gerar_codigo_fonte(raiz: Path, arquivos: list[ArquivoInfo]) -> str:
    partes: list[str] = []
    for arquivo in arquivos:
        texto = ler_texto(arquivo.caminho)
        fence = "```"
        while fence in texto:
            fence += "`"
        partes.append(f"### `{arquivo.relativo}`\n")
        partes.append(
            f"- Linhas: {arquivo.linhas}\n"
            f"- Tamanho: {tamanho_humano(arquivo.tamanho)}\n"
            f"- Caminho absoluto: `{arquivo.caminho}`\n"
        )
        partes.append(f"{fence}{linguagem(arquivo.caminho)}\n{texto.rstrip()}\n{fence}\n")
    return "\n".join(partes)


def gerar_manual_md(raiz: Path) -> str:
    agora = datetime.now()
    data = agora.strftime("%d/%m/%Y %H:%M:%S")
    arquivos = listar_arquivos_texto(raiz)
    imagens = listar_imagens(raiz)
    trabalhos = listar_trabalhos(raiz)
    total_linhas = sum(a.linhas for a in arquivos)
    total_bytes = sum(a.tamanho for a in arquivos)

    return f"""# Manual Tecnico CriaVibe

![CriaVibe](../../logo/logo-criavibe-fotografia.png)

> **Projeto:** {PROJETO}
> **Responsavel tecnico:** {RESPONSAVEL_TECNICO}
> **Registrador:** {REGISTRADOR}
> **Gerado em:** {data}
> **Origem:** `{raiz}`

---

## 1. Capa e Identificacao

Este manual e gerado automaticamente por `agente-willianbo/scripts/gerar_manual.py`.
Ele consolida a estrutura do repositorio, arquivos textuais, codigos-fonte,
registros tecnicos e imagens do projeto CriaVibe em um unico artefato rastreavel.

Arquivos sensiveis e artefatos pesados sao omitidos de proposito: `.env`, `.git/`,
`uploads/`, logs, dependencias de terceiros e o proprio manual gerado.

---

## 2. Indice

{gerar_indice()}

---

## 3. Sumario Executivo

- Total de arquivos textuais documentados: **{len(arquivos)}**
- Total de linhas de codigo/documentacao: **{total_linhas}**
- Tamanho textual documentado: **{tamanho_humano(total_bytes)}**
- Imagens inventariadas: **{len(imagens)}**
- Registros de trabalho consolidados: **{len(trabalhos)}**

---

## 4. Stack e Arquitetura

- Frontend: HTML, CSS e JavaScript Vanilla.
- Backend: PHP nativo em `api/`.
- Banco de dados: MySQL.
- Deploy: Railway com Docker.
- Storage de midia: Cloudflare R2.
- Filas e processamento: Redis e worker PHP.
- Documentacao tecnica: Markdown gerado em `documentacao/manual/`.

Entradas principais:

- `index.html`
- `entrar.html`
- `painel.html`
- `galeria.html`
- `cliente.html`
- `api/config.php`
- `api/db_migrations.php`
- `Dockerfile`
- `router.php`

---

## 5. Hierarquia de Pastas e Subpastas

```text
{gerar_arvore(raiz)}
```

---

## 6. Inventario Completo de Arquivos

{gerar_inventario(arquivos)}

---

## 7. Imagens e Midias do Projeto

{gerar_imagens(imagens)}

---

## 8. Registros de Trabalho em Ordem Cronologica

{gerar_registros_trabalho(raiz, trabalhos)}

---

## 9. Codigo Fonte Completo

{gerar_codigo_fonte(raiz, arquivos)}

---

## 10. Criterios de Regeneracao

Para atualizar este manual, execute:

```bash
python agente-willianbo/scripts/gerar_manual.py
```

Saidas esperadas:

- `documentacao/manual/Manual_Tecnico_CriaVibe.md`
- `documentacao/manual/Manual_Tecnico_CriaVibe.pdf`

O Markdown e a fonte integral e auditavel. O PDF e uma versao paginada para leitura,
revisao e entrega.
"""


def quebrar_linhas(texto: str, largura: int) -> list[str]:
    linhas: list[str] = []
    for linha in texto.splitlines():
        if not linha.strip():
            linhas.append("")
            continue
        if linha.startswith("```"):
            linhas.append(linha)
            continue
        linhas.extend(textwrap.wrap(linha, width=largura, replace_whitespace=False) or [""])
    return linhas


def inserir_pagina_texto(doc, titulo: str | None = None):
    import fitz

    pagina = doc.new_page(width=595, height=842)
    if titulo:
        pagina.insert_text((48, 56), titulo, fontsize=16, fontname="helv", color=(0.16, 0.13, 0.28))
    return pagina


def gerar_pdf_com_fitz(md: str, destino_pdf: Path, raiz: Path, imagens: list[ArquivoInfo]) -> None:
    import fitz

    doc = fitz.open()
    doc.set_metadata(
        {
            "title": "Manual Tecnico CriaVibe",
            "author": RESPONSAVEL_TECNICO,
            "subject": "Manual tecnico completo do sistema CriaVibe",
            "keywords": "CriaVibe, manual tecnico, codigo fonte, documentacao",
            "creator": REGISTRADOR,
        }
    )
    margem_x = 48
    margem_topo = 72
    margem_rodape = 48
    largura_linha = 92
    fonte = "courier"
    tamanho = 8
    altura_linha = 10

    capa = doc.new_page(width=595, height=842)
    logo = raiz / "logo" / "logo-criavibe-fotografia.png"
    if logo.exists():
        try:
            capa.insert_image(fitz.Rect(160, 92, 435, 210), filename=str(logo), keep_proportion=True)
        except Exception:
            pass
    capa.insert_text((92, 275), "Manual Tecnico CriaVibe", fontsize=28, fontname="helv", color=(0.16, 0.13, 0.28))
    capa.insert_text((92, 320), "Documentacao completa do sistema", fontsize=14, fontname="helv", color=(0.30, 0.30, 0.36))
    capa.draw_line((92, 350), (503, 350), color=(0.55, 0.50, 0.85), width=1)
    capa.insert_text((92, 395), f"Responsavel tecnico: {RESPONSAVEL_TECNICO}", fontsize=12, fontname="helv")
    capa.insert_text((92, 420), f"Registrador: {REGISTRADOR}", fontsize=12, fontname="helv")
    capa.insert_text((92, 445), f"Gerado em: {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}", fontsize=12, fontname="helv")
    capa.insert_text((92, 500), "Conteudo: estrutura, inventario, registros de trabalho, codigo fonte e imagens.", fontsize=10, fontname="helv")

    pagina = inserir_pagina_texto(doc, "Indice e Sumario")
    y = margem_topo + 20

    for linha in quebrar_linhas(md, largura_linha):
        if y > 842 - margem_rodape:
            pagina = inserir_pagina_texto(doc)
            y = margem_topo
        if linha.startswith("# "):
            y += 10
            pagina.insert_text((margem_x, y), linha[2:90], fontsize=14, fontname="helv", color=(0.16, 0.13, 0.28))
            y += 18
        elif linha.startswith("## "):
            y += 8
            pagina.insert_text((margem_x, y), linha[3:95], fontsize=12, fontname="helv", color=(0.20, 0.18, 0.35))
            y += 16
        elif linha.startswith("### "):
            y += 6
            pagina.insert_text((margem_x, y), linha[4:100], fontsize=10, fontname="helv", color=(0.24, 0.22, 0.40))
            y += 13
        elif linha.startswith("!"):
            continue
        else:
            pagina.insert_text((margem_x, y), linha[:120], fontsize=tamanho, fontname=fonte, color=(0.05, 0.05, 0.05))
            y += altura_linha

    if imagens:
        pagina = inserir_pagina_texto(doc, "Anexo Visual - Imagens do Projeto")
        y = 92
        for imagem in imagens:
            if y > 650:
                pagina = inserir_pagina_texto(doc, "Anexo Visual - Imagens do Projeto")
                y = 92
            pagina.insert_text((margem_x, y), imagem.relativo, fontsize=9, fontname="helv")
            y += 12
            try:
                rect = fitz.Rect(margem_x, y, 545, min(y + 180, 780))
                pagina.insert_image(rect, filename=str(imagem.caminho), keep_proportion=True)
                y += 195
            except Exception:
                pagina.insert_text((margem_x, y), "[Imagem nao inserida no PDF; ver caminho no Markdown.]", fontsize=8)
                y += 20

    total = doc.page_count
    for idx, pagina in enumerate(doc, start=1):
        pagina.insert_text((500, 820), f"{idx}/{total}", fontsize=8, color=(0.35, 0.35, 0.35))
    doc.save(destino_pdf)
    doc.close()


def gerar_pdf_fallback(md: str, destino_pdf: Path) -> None:
    linhas = quebrar_linhas(md, 88)
    paginas = [linhas[i : i + 72] for i in range(0, len(linhas), 72)]
    objetos: list[str] = []

    def add(obj: str) -> int:
        objetos.append(obj)
        return len(objetos)

    fontes_id = add("<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>")
    page_ids: list[int] = []
    content_ids: list[int] = []
    for numero, pagina_linhas in enumerate(paginas, start=1):
        comandos = ["BT", "/F1 8 Tf", "48 800 Td"]
        for linha in pagina_linhas:
            safe = linha.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")
            comandos.append(f"({safe[:115]}) Tj")
            comandos.append("0 -10 Td")
        comandos.append(f"(Pagina {numero}/{len(paginas)}) Tj")
        comandos.append("ET")
        stream = "\n".join(comandos)
        content_id = add(f"<< /Length {len(stream.encode('latin-1', errors='replace'))} >>\nstream\n{stream}\nendstream")
        content_ids.append(content_id)
        page_ids.append(0)

    pages_id = len(objetos) + len(paginas) + 1
    for i, content_id in enumerate(content_ids):
        page_ids[i] = add(
            f"<< /Type /Page /Parent {pages_id} 0 R /MediaBox [0 0 595 842] "
            f"/Resources << /Font << /F1 {fontes_id} 0 R >> >> /Contents {content_id} 0 R >>"
        )
    kids = " ".join(f"{pid} 0 R" for pid in page_ids)
    pages_id = add(f"<< /Type /Pages /Kids [{kids}] /Count {len(page_ids)} >>")
    catalog_id = add(f"<< /Type /Catalog /Pages {pages_id} 0 R >>")

    pdf = ["%PDF-1.4\n"]
    offsets: list[int] = []
    for idx, obj in enumerate(objetos, start=1):
        offsets.append(sum(len(p.encode("latin-1", errors="replace")) for p in pdf))
        pdf.append(f"{idx} 0 obj\n{obj}\nendobj\n")
    xref = sum(len(p.encode("latin-1", errors="replace")) for p in pdf)
    pdf.append(f"xref\n0 {len(objetos) + 1}\n0000000000 65535 f \n")
    for offset in offsets:
        pdf.append(f"{offset:010d} 00000 n \n")
    pdf.append(f"trailer\n<< /Size {len(objetos) + 1} /Root {catalog_id} 0 R >>\nstartxref\n{xref}\n%%EOF\n")
    destino_pdf.write_bytes("".join(pdf).encode("latin-1", errors="replace"))


def gerar_pdf(md: str, destino_pdf: Path, raiz: Path, imagens: list[ArquivoInfo]) -> None:
    try:
        gerar_pdf_com_fitz(md, destino_pdf, raiz, imagens)
    except Exception as exc:
        print(f"Aviso: geracao com PyMuPDF falhou ({exc}). Usando PDF textual fallback.")
        gerar_pdf_fallback(md, destino_pdf)


def main() -> None:
    raiz = encontrar_raiz()
    destino = raiz / "documentacao" / "manual"
    destino.mkdir(parents=True, exist_ok=True)

    arquivo_md = destino / "Manual_Tecnico_CriaVibe.md"
    arquivo_pdf = destino / "Manual_Tecnico_CriaVibe.pdf"

    manual = gerar_manual_md(raiz)
    arquivo_md.write_text(manual, encoding="utf-8", newline="\n")
    gerar_pdf(manual, arquivo_pdf, raiz, listar_imagens(raiz))

    print(f"Manual Markdown gerado em: {arquivo_md}")
    print(f"Manual PDF gerado em: {arquivo_pdf}")


if __name__ == "__main__":
    main()
