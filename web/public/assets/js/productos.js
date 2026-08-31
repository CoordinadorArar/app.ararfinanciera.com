document.addEventListener('DOMContentLoaded', function () {
    // Abrir todas las preguntas por defecto
    document.querySelectorAll('.faq-item').forEach(item => {
      item.classList.add('active');
    });
  
    // Agrega el evento SOLO al header de la pregunta
    //document.querySelectorAll('.faq-question').forEach(question => {
      //question.addEventListener('click', () => {
       // const item = question.parentElement;
        //item.classList.toggle('active');
      //});
    //});
  });
  