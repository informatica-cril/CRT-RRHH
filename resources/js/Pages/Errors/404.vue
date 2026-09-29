
<Head title="Error 404" />
<template>
  <div class="pagina-404">
    <h1>Error 404</h1>
    <p> {{ $page.props.error }} </p>
  </div>
</template>

<style scoped>

.pagina-404 {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  text-align: center;
}
.pagina-404 h1 {
  font-size: 4rem;
  color: #e53e3e;
}
.pagina-404 p {
  font-size: 1.5rem;
  color: #555;
}
</style>
