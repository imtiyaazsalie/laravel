{% extends 'layout/pdf.html.twig' %}

{% block customStyles %}
    .margin-top-small {
        margin-top: 10px !important;
    }
{% endblock %}

{% block title %}Statement for {{ startDate }} - {{ endDate }}{% endblock %}

{% block content %}
    {% include 'emails/finance/statement/statement-content.html.twig' %}
{% endblock %}
