"""
SEO Report PDF Generator.

Produces a multi-page PDF summarising an SeoAudit: a front page with the
overall score and severity counts, followed by issue pages grouped by
severity.
"""
import io
from datetime import datetime

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm, mm
from reportlab.platypus import (
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

# ---------------------------------------------------------------------------
# Colour palette
# ---------------------------------------------------------------------------

BRAND_BLUE = colors.HexColor('#1a3a5c')
CRITICAL_RED = colors.HexColor('#dc3545')
WARNING_AMBER = colors.HexColor('#ffc107')
INFO_TEAL = colors.HexColor('#17a2b8')
LIGHT_GREY = colors.HexColor('#f8f9fa')

SEVERITY_COLOURS = {
    'critical': CRITICAL_RED,
    'warning': WARNING_AMBER,
    'info': INFO_TEAL,
}


def generate_report(audit) -> bytes:
    """Generate a PDF report from an SeoAudit instance.

    Returns the raw PDF bytes -- the caller can serve them as an HTTP
    response or write them to a file.
    """
    buf = io.BytesIO()
    doc = SimpleDocTemplate(
        buf,
        pagesize=A4,
        leftMargin=2 * cm,
        rightMargin=2 * cm,
        topMargin=2 * cm,
        bottomMargin=2 * cm,
    )

    styles = getSampleStyleSheet()

    # Custom styles
    title_style = ParagraphStyle(
        'AuditTitle',
        parent=styles['Title'],
        fontSize=28,
        textColor=BRAND_BLUE,
        spaceAfter=12 * mm,
    )
    heading_style = ParagraphStyle(
        'AuditHeading',
        parent=styles['Heading2'],
        fontSize=16,
        textColor=BRAND_BLUE,
        spaceBefore=8 * mm,
        spaceAfter=4 * mm,
    )
    body_style = styles['BodyText']
    small_style = ParagraphStyle(
        'Small',
        parent=body_style,
        fontSize=8,
        textColor=colors.grey,
    )

    elements: list = []

    # ---- Page 1: Summary --------------------------------------------------

    elements.append(Paragraph('SEO Audit Report', title_style))
    elements.append(Paragraph(
        f'Generated: {datetime.now():%Y-%m-%d %H:%M}',
        small_style,
    ))
    elements.append(Spacer(1, 10 * mm))

    # Score display
    score_colour = (
        CRITICAL_RED if audit.score < 50
        else WARNING_AMBER if audit.score < 80
        else INFO_TEAL
    )
    score_style = ParagraphStyle(
        'Score',
        parent=styles['Title'],
        fontSize=72,
        textColor=score_colour,
        alignment=1,  # centre
    )
    elements.append(Paragraph(str(audit.score), score_style))
    elements.append(Paragraph(
        '<para alignment="center">Overall Score (0 - 100)</para>',
        body_style,
    ))
    elements.append(Spacer(1, 10 * mm))

    # Summary table
    summary_data = [
        ['Metric', 'Count'],
        ['Pages Scanned', str(audit.pages_scanned)],
        ['Critical Issues', str(audit.critical_count)],
        ['Warnings', str(audit.warning_count)],
        ['Info', str(audit.info_count)],
        ['Total Issues', str(
            audit.critical_count + audit.warning_count + audit.info_count,
        )],
    ]
    summary_table = Table(summary_data, colWidths=[10 * cm, 5 * cm])
    summary_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), BRAND_BLUE),
        ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
        ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
        ('FONTSIZE', (0, 0), (-1, -1), 10),
        ('ALIGN', (1, 0), (1, -1), 'CENTER'),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, LIGHT_GREY]),
        ('GRID', (0, 0), (-1, -1), 0.5, colors.grey),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
    ]))
    elements.append(summary_table)

    # ---- Page 2+: Issues grouped by severity ------------------------------

    issues = list(audit.issues.all().order_by('severity', 'issue_type'))
    if issues:
        elements.append(Paragraph('Issue Details', heading_style))

        for severity in ('critical', 'warning', 'info'):
            group = [i for i in issues if i.severity == severity]
            if not group:
                continue

            sev_colour = SEVERITY_COLOURS[severity]
            elements.append(Paragraph(
                f'{severity.upper()} ({len(group)})',
                ParagraphStyle(
                    f'Sev_{severity}',
                    parent=heading_style,
                    fontSize=14,
                    textColor=sev_colour,
                ),
            ))

            table_data = [['Type', 'URL', 'Message']]
            for issue in group:
                # Truncate long URLs for readability
                short_url = (
                    issue.url[:60] + '...'
                    if len(issue.url) > 60
                    else issue.url
                )
                table_data.append([
                    Paragraph(issue.issue_type, body_style),
                    Paragraph(short_url, small_style),
                    Paragraph(issue.message, body_style),
                ])

            col_widths = [4 * cm, 5 * cm, 7 * cm]
            issue_table = Table(table_data, colWidths=col_widths,
                                repeatRows=1)
            issue_table.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (-1, 0), sev_colour),
                ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
                ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, -1), 8),
                ('ROWBACKGROUNDS', (0, 1), (-1, -1),
                 [colors.white, LIGHT_GREY]),
                ('GRID', (0, 0), (-1, -1), 0.5, colors.grey),
                ('VALIGN', (0, 0), (-1, -1), 'TOP'),
                ('TOPPADDING', (0, 0), (-1, -1), 3),
                ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
            ]))
            elements.append(issue_table)
            elements.append(Spacer(1, 5 * mm))
    else:
        elements.append(Paragraph(
            'No issues found -- your site scores a perfect 100!',
            body_style,
        ))

    # Build
    doc.build(elements)
    return buf.getvalue()
